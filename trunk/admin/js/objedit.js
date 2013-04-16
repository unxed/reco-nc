
// inspired by http://javascript.ru/tutorial/object/inheritance#fabrika-obiektov-moi-liubimyi-sposob

function ObjEditor(initId, placeId) {
    // этот код выполняется при первичной инициализации
    // при этом определяется, какой из классов-потомков нам нужен
    // и создается (и возвращается) экземпляр этого класса
    if (initId) {
        // hackfix: нельзя создавать больше одного редактора в одном объекте.
        // на самом деле, правильный вариант: нельзя создавать больше одного редактора в одном placeId

        if (window.editId!=undefined) {
            if ((window.ObjEditors!=undefined) && (window.ObjEditors[window.editId]!=undefined)) {

                    window.ObjEditors[window.editId].close(function(){
                        // Previous editor's data are saved.
                        // Recreating new editor.
                        edit(initId);
                    });

                    // Terminate current editor while waiting for previous editor to close.
                    return false;

            } else {

                return false; // другой редактор не успел инициализироваться
            }
        }

        // установим глобальный id редактируемого объекта
        window.editId = initId;

        $.ajax({
            type: "GET",
            url: "php/initedit.php",
            cache: false,
            data: "id="+initId,
            error: function (xhr, ajaxOptions, thrownError) { alert('Error #' + xhr.status); undoEditorOpen(); },
            success: function(msg){
                    
                if (msg == -1) {
                    alert('Этот объект уже редактирует другой пользователь.');

                    undoEditorOpen();
                } else if (msg == 'access denied') {
                    alert('Недостаточно прав доступа для чтения данного объекта.');

                    undoEditorOpen();
                } else {
                    UniEditor(initId, msg, placeId);
                }
            }
        });

    }
    // этот код выполняется при обращении к конструктору из класса-потомка
    // возвращает методы класса
    else return {
        // переменные класса
        id: null,
        classId: null,
        container: null,
        html: null,
        properties: null,
        changesCount: null,
        // методы класса
        PutOnPage: function(containerId) {
            this.container = containerId;

            html = this.generateHTML();

            $('#' + this.container).append(html);
        },
        Init: function(id_, classId_, placeId) { this.id = id_; this.classId = classId_; if (placeId) { this.PutOnPage(placeId); } },
        close: function(onOk) {

            if ((this.changesCount > 0) && confirm('Сохранить сделанные изменения?')) {
                that = this;
                window.ObjEditors[this.id].save(function() {
                    that.unconditionalClose();
                    if (onOk != undefined) { onOk(); }
                });
            } else {
                this.unconditionalClose();
                if (onOk != undefined) { onOk(); }
            }
        },
        unconditionalClose: function() {

            // снимем выделение текущего объекта красным
            undoEditorOpen();

            // удалим его контейнер
            $('#' + this.container).html('');

            // send unlock request
            $.ajax({
                type: "GET",
                url: "php/unlock.php",
                cache: false,
                data: "id="+this.id,
                success: function(msg){ }
            });
        }

    }
}

function UniEditor(id, classId, placeId) {
    // === <Стандартный код для всех потомков ObjEditor> ===
    // создание базового объекта
    me = ObjEditor();
    // === </Стандартный код для всех потомков ObjEditor> ===

    me.changesCount = 0;

    // переопределение методов
    superPutOnPage = me.PutOnPage;
    me.PutOnPage = function(containerid) {
        // сначала загрузим контент, а потом уже будет публиковать редактор

        $.ajax({
            type: "GET",
            url: "php/uquery.php",
            cache: false,
            data: "class=" + classId + "&id=" + id, // query вернет массив элементов объекта в json
            success: function(msg){
                    me.properties = eval( "(" + msg + ")" );
                    superPutOnPage.call(me, containerid);

                    me.initPage();
                }
        });
    }

    me.initPage = function() {
        if (me.properties!=undefined) { 
            for (var i in me.properties.elements) {
                // datatype
                switch (me.properties.elements[i].type) {
                    case '2':

                        me.properties.elements[i].mceCfg = {

                            mode : "exact",
                            elements :  'id'+id+'element'+me.properties.elements[i].id,

                            theme : "advanced",

                            plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,images,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

                            // Theme options
                            // |,forecolor,backcolor,|,
                            theme_advanced_buttons1 : "bold,italic,underline,formatselect,link,justifyleft,justifycenter,justifyright,|,pasteword,pastetext,table,images,|,bullist,numlist,|,undo,redo,|,code,fullscreen",
                            theme_advanced_buttons2 : "",
                            theme_advanced_buttons3 : "",
                            theme_advanced_toolbar_location : "top",
                            theme_advanced_toolbar_align : "left",
                            theme_advanced_statusbar_location : "bottom",
                            theme_advanced_resizing : true,

                            //Path
                            relative_urls : false,
                            remove_script_host : true,

                            convert_urls : false,

                            // Example content CSS (should be your site CSS)
                            content_css : "css/content.css?" + Math.random(),

                            language : "ru",

                            setup : function(ed) {
                                    // считаем все изменения и фиксируем их в значении textarea

                                    // моментальное обновление textareat.value, возможно,
                                    // не лучшее решение с точки зрения производительности,
                                    // но, похоже, нет другого способа сохранить данные редактора
                                    // перед переходом в другой объект (т.к. сам редактор,
                                    // по-видимому, деинициализируется раньше, чем срабатывает мой обработчк onUnload)

                                    ed.onChange.add(function(ed, l) { me.changesCount++; tinyMCE.triggerSave(false, false); });
                                    ed.onKeyDown.add(function(ed, e) { me.changesCount++; tinyMCE.triggerSave(false, false); });
                                    ed.onPaste.add(function(ed, e, o) { me.changesCount++; tinyMCE.triggerSave(false, false); });

                                    ed.onKeyUp.add(function(ed, e) { tinyMCE.triggerSave(false, false); });
                                    ed.onNodeChange.add(function(ed, e, o) { tinyMCE.triggerSave(false, false); });
                                }
                        };

                        tinyMCE.settings = me.properties.elements[i].mceCfg;
                        tinyMCE.execCommand('mceAddControl', true, 'id'+id+'element'+me.properties.elements[i].id);

                        break;

                    case '11':
                        me.updateFlash(me.properties.elements[i].id, me.properties.elements[i].w, me.properties.elements[i].h);
                        break;

                    case '12':
                        me.updateVideo(me.properties.elements[i].id, me.properties.elements[i].w, me.properties.elements[i].h);
                        break;

                    case '4':
                        // update images
                        me.updateImages(me.properties.elements[i].id);

                        if (me.properties.elements[i].maxcnt > 1)
                        {
                            // init swfupload
                            defaultId = 'id'+id+'element'+me.properties.elements[i].id; // fixme: change be code below to use it

                            if(window.swfUploads==undefined) { window.swfUploads = new Array(); }
                            window.swfUploads[defaultId] = new SWFUpload({
                                // Backend Settings
                                upload_url: "php/upload.php",
                                //post_params: {"PHPSESSID" : "<?php echo session_id(); ?>"},
                                post_params : {
                                    "ref" : id,
                                    "el" : me.properties.elements[i].id,
                                    "PHPSESSID" : Get_Cookie('PHPSESSID')
                                },

                                // File Upload Settings
                                file_size_limit : "10240",  // 10MB
                                file_types : "*.*",
                                file_types_description : "All Files",
                                file_upload_limit : "0",
                                file_queue_limit : "0",

                                // Event Handler Settings (all my handlers are in the Handler.js file)
                                file_dialog_start_handler : fileDialogStart,
                                file_queued_handler : fileQueued,
                                file_queue_error_handler : fileQueueError,
                                file_dialog_complete_handler : fileDialogComplete,
                                upload_start_handler : uploadStart,
                                upload_progress_handler : uploadProgress,
                                upload_error_handler : uploadError,
                                upload_success_handler : uploadSuccess,
                                upload_complete_handler : uploadComplete,
                                queue_complete_handler : queueComplete,
            
                                // Button Settings
                                button_image_url : "swfupload/upload.png",
                                button_placeholder_id : "uploadBtn" + '_' + defaultId,
                                button_width: 61,
                                button_height: 22,
                
                                // Flash Settings
                                flash_url : "swfupload/swfupload.swf",

                                custom_settings : {
                                    progressTarget : "progressBar" + '_' + defaultId,
                                    cancelButtonId : "cancelBtn" + '_' + defaultId,

                                    statusBar : "statusBar" + '_' + defaultId,
                                    elementId : me.properties.elements[i].id,
                                    contaner : me
                                },

                                // Debug Settings
                                debug: false
                            });
                        }

                        break;
                }
            }
        }
    }

    me.generateHTML = function() {
        if (me.properties != undefined) {
            out = '<div id=innerbox' + id + '>';

            out += '<strong><a href=javascript:;><span onclick=window.ObjEditors[' + id + '].save();>Сохранить</span></a> | ' +
            '<a href=javascript:;><span onclick=javascript:window.ObjEditors[' + id + '].close(); style="color: red;">Закрыть</span></a>' +
            ' | ' + 'Класс: ' + me.properties.name;

            // fixme: access rights manipulation is untested and temporay disabled
            // if (edit_rights == 1) { out += ' | <a target=_blank href=php/rights.php?object=' + id + '>Права доступа</a>'; }

            out += '</strong><br/><br/>';

            for (var i in me.properties.elements) {
                el_i = me.properties.elements[i];

                if (el_i.value == null) {
                    value = '';
                }

                defaultId = 'id'+id+'element'+el_i.id; // fixme: change be code below to use it

                dname = el_i.name;
                dval = el_i.value;

                // datatype
                switch (el_i.type) {
                    case '1':
                        out += '<table style="border-bottom: 1px dashed #ccc; margin: 0; padding:0;"><tr><td style="width: 240px;">' + dname + ' </td><td>' +
                        '<select id='+defaultId+' name='+defaultId+' onChange=window.ObjEditors['+id+'].changesCount++; onKeyDown=window.ObjEditors['+id+'].changesCount++;>';

                        for ( var lst in el_i.list ) {
                            if (dval == lst) { sel = 'selected'; } else { sel = ''; }
                            out += '<option value='+lst+' '+sel+'>'+el_i.list[lst];
                        }
                        out += '</select></td></tr></table>';
                        break;
                    case '2':
                        out += '<p style="margin-left:0px; margin-top:10px;">' + 
                        dname + ' <br>' +
                        '<textarea id='+defaultId+' name='+defaultId+' rows="12" cols="80">'+
                        dval+'</textarea></p>';

                        break;
                    case '3':
                    case '10':
                    case '14':
                        out += '<table style="border-bottom: 1px dashed #ccc; margin: 0; padding:0;"><tr><td style="width: 240px;">' + dname + ' </td><td>' +
                        '<input id='+defaultId+' onChange=window.ObjEditors['+id+'].changesCount++; onKeyDown=window.ObjEditors['+id+'].changesCount++;' +
                        'name='+defaultId+' value="' + dval + '" size=64></td></tr></table>';
                        break;
                    case '15':
                        out += '<table style="border-bottom: 1px dashed #ccc; margin: 0; padding:0;"><tr><td style="width: 240px;">' + dname + ' </td><td>' +
                        '<input type="checkbox" id="'+defaultId+'" onChange="window.ObjEditors['+id+'].changesCount++;" onKeyDown=window.ObjEditors['+id+'].changesCount++;' +
                        'name="'+defaultId+'" ' + ((dval == 1) ? 'checked="checked"' : '') + '></td></tr></table>';
                        break;
                    case '4':
                        out += '<b>' + dname + '</b>' +
                        ' <small>(чтобы удалить изобрежение, щелкните по нему левой кнопкой мыши)</small><br>' +
                        '<div id=img_'+defaultId+'></div>' +
                        '<form id='+defaultId+' method=post action=php/upload.php target=upload_'+defaultId+' enctype="multipart/form-data">' +

                        'Добавить изображение:<input name=imgadd size=1 type=file onChange="e=window.ObjEditors['+id+'].properties.elements['+i+'];if(e.count<e.maxcnt){this.form.submit();this.value=\'\';}else{alert(\'Количество допустимых здесь иллюстраций: \'+e.maxcnt);}">' +
                        '<input name=ref type=hidden value='+id+'><input name=el type=hidden value='+el_i.id+'></form>' +
                        '<iframe name=upload_'+defaultId+' height=1 width=1 frameborder=0></iframe>';

                        if (el_i.maxcnt > 1) {
                            out += '<!--<br>-->' +
                            'Добавить группу изображений:' +
                            '<style>.swfupload { vertical-align: top; }</style>' +
                            '<div style="width: 120px; border: 1px solid blue; color: #888; ">' +
                            '<div style="background-color: blue; height: 20px; width: 0px; text-align: center; " id="progressBar_'+defaultId+'">' +
                            '</div></div>' +
                            '<div id=statusBar_'+defaultId+'>&nbsp;</div>' +
                            '<span id="uploadBtn_'+defaultId+'"></span>' +
                            '<input id="cancelBtn_'+defaultId+'" type="button" value="Cancel" onclick="cancelQueue(upload1);" disabled="disabled" style="margin-left: 2px; height: 22px; font-size: 8pt; margin: 0; padding: 0; border: 0; font-size: 16px;"><br>';
                        }

                        break;

                    case '11':
                        out += '<b>' + dname + '</b> <small>(чтобы удалить, поставьте высоту и ширину в 0)</small><br>';

                        out += '<p style="margin-left:0px;">Ширина: ' +
                        '<input id='+defaultId+'_w onChange=window.ObjEditors['+id+'].changesCount++; ' +
                        'name='+defaultId+'_w value="' + el_i.w + '" size=5></p>';

                        out += '<p style="margin-left:0px;">Высота: ' +
                        '<input id='+defaultId+'_h onChange=window.ObjEditors['+id+'].changesCount++; ' +
                        'name='+defaultId+'_h value="' + el_i.h + '" size=5></p>';

                        out += '<div id=flash_'+defaultId+' style="margin: 5px; padding: 5px; border: 1px dashed #ccc; float: left;">';
                        out += '</div><br><br><br><br><br><br><br>';
                        out += '<form id='+defaultId+' method=post action=php/upload_f.php target=upload_'+defaultId+' enctype="multipart/form-data">' +

                        'Добавить/заменить flash:<input name=imgadd size=1 type=file onChange="this.form.submit();">' +
                        '<input name=ref type=hidden value='+id+'><input name=el type=hidden value='+el_i.id+'></form>' +
                        '<iframe name=upload_'+defaultId+' height=1 width=1 frameborder=0></iframe>';

                        break;

                    case '12':
                        out += '<b>' + dname + '</b> <small>(чтобы удалить, поставьте высоту и ширину в 0)</small><br>';

                        out += '<p style="margin-left:0px;">Ширина: ' +
                        '<input id='+defaultId+'_w onChange=window.ObjEditors['+id+'].changesCount++; ' +
                        'name='+defaultId+'_w value="' + el_i.w + '" size=5></p>';

                        out += '<p style="margin-left:0px;">Высота: ' +
                        '<input id='+defaultId+'_h onChange=window.ObjEditors['+id+'].changesCount++; ' +
                        'name='+defaultId+'_h value="' + el_i.h + '" size=5></p>';

                        out += '<div id=video_'+defaultId+' style="margin: 5px; padding: 5px; border: 1px dashed #ccc; width: 270px; height: 150px; float: none;">';
                        out += '</div><br>';

                        out += '<form id='+defaultId+' method=post action=php/upload_v.php target=upload_'+defaultId+' enctype="multipart/form-data">' +

                        'Добавить/заменить видео:<input name=imgadd size=1 type=file onChange="this.form.submit();">' +
                        '<input name=ref type=hidden value='+id+'><input name=el type=hidden value='+el_i.id+'></form>' +
                        '<iframe name=upload_'+defaultId+' height=1 width=1 frameborder=0></iframe>';

                        break;

                    case '8':
                        out += dname + '<br>';
                        break;
                    case '9':
                        out += '<br><div style="display: table-cell;">' + dname + '&nbsp;&nbsp;<hr></div>';
                        break;
                    case '13':
                        out += '<p style="margin-left:0px; margin-top:0px;">' + dname + ' <br>';
                        for ( var lst in el_i.list ) {
                            sel = '';
                            var vals = dval.split(',');
                            // fixme: optimize this
                            for ( key in vals )
                            {
                                if ('id'+vals[key] == lst) { sel = 'checked'; }
                            }
                            out += '<input type=checkbox id=' + defaultId + 'sub' + lst + ' ' + sel +
                            ' onChange=window.ObjEditors['+id+'].changesCount++; onKeyDown=window.ObjEditors['+id+'].changesCount++;>'+el_i.list[lst]+'<br>';
                        }
                        out += '</p>';
                        break;
                }
            }

            out += '</div>';

            return out;
        }
    }

    me.clearFlash = function(elementId) {
        $('#flash_id'+id+'element'+elementId).html('');
    }

    me.updateFlash = function(elementId, w, h) {
        if (w == 0) return;
        
        uniq = Math.random();

        var out = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=5,0,0,0" width="180" height="100">';
        out += '<param name=movie value="../storage/'+id+'_'+elementId+'.swf?rand='+uniq+'"><param name=quality value=high>';
        out += '<embed src="../storage/'+id+'_'+elementId+'.swf?rand='+uniq+'" quality=high pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" width="180" height="100"></embed></object>';

        $('#flash_id'+id+'element'+elementId).html(out);
    }

    me.clearVideo = function(elementId) {
        $('#video_id'+id+'element'+elementId).html('');
    }

    me.updateVideo = function(elementId, w, h) {
        if (w == 0) return;
        
        uniq = Math.random();

        var out = 'video';

        // !! если видео файла нет, не выводить плеер

        var out = '<a href="../storage/'+id+'_'+elementId+'.flv?rand='+uniq+'" style="display:block;width:270px;height:150px" id="player_'+elementId+'"></a>';

        $('#video_id'+id+'element'+elementId).html(out);

        flowplayer("player_"+elementId, "swf/flowplayer-3.1.5.swf", {clip: {autoPlay: false, autoBuffering: true}});
    }

    me.updateImages = function(elementId) {
        $.ajax({
            type: "GET",
            url: "php/uquery.php",
            cache: false,
            data: "class=" + classId + "&id="+id, // query вернет массив элементов объекта в json
            success: function(msg) {

                    me.properties = eval( "(" + msg + ")" );

                    if (me.properties == undefined) { return; }

                    elementIndex = '';
                    for (var i in me.properties.elements)
                    {
                        if (me.properties.elements[i].id == elementId) { elementIndex = i; }
                    }

                    me.properties = eval( "(" + msg + ")" );

                    content = '';

                    if (me.properties.elements[elementIndex] != undefined)
                    for ( var imgNum in me.properties.elements[elementIndex].images) {
                        img_el = me.properties.elements[elementIndex].images[imgNum];
                        img = img_el.id;

                        uniq = Math.random();

                        grn = 'style="color: black;"'; grh = 'color: black;';

                        modn = true; modh = true;

                        // hackfix & hardcoded strings: поля не могут принимать реальное значение null,
                        // цвет css style используется для определения статуса строки ввода
                        // " || (name == 'null')" - chrome bug?
                        name = img_el.name; if ((name == null) || (name == 'null') || (name == '')) { name = 'Название'; grn = 'style="color: gray;"'; modn = false; } 
                        href = img_el.href; if ((href == null) || (href == 'null') || (href == '')) { href = 'Ссылка'; grh = 'style="color: gray;"'; modh = false; } 

                        content += '<div style="border:1px dashed #ccc; padding: 4px; margin-right: 4px; float: left;" id=img'+img+'>' +
                        '<img width=180 height=100 src=../storage/pre_'+img+'.jpg?'+uniq+' onClick="if(confirm(\'Удалить эту иллюстрацию?\')){window.ObjEditors['+me.id+'].delImage('+img+','+elementId+');}">';

                        if (me.properties.elements[elementIndex].imgDesc == 1)
                        {
                            content += '<br><textarea '+grn+' cols=20 rows=4 id=img_name_id'+id+'element'+elementId+'img'+img+' onFocus="if(this.modified==undefined){this.modified=' + modn +';}if(!this.modified){this.value=\'\';this.style.color=\'black\';this.modified=true;}" onBlur="if(this.value==\'\'){this.value=\'Название\';this.style.color=\'gray\';this.modified=false;}">'+name+'</textarea>';
                        } else {
                            content += '<br><input '+grn+' id=img_name_id'+id+'element'+elementId+'img'+img+' value="'+name+'" onFocus="if(this.modified==undefined){this.modified=' + modn +';}if(!this.modified){this.value=\'\';this.style.color=\'black\';this.modified=true;}" onBlur="if(this.value==\'\'){this.value=\'Название\';this.style.color=\'gray\';this.modified=false;}">';
                        }

                        content += '<br><input '+grh+' id=img_href_id'+id+'element'+elementId+'img'+img+' value="'+href+'" onFocus="if(this.modified==undefined){this.modified=' + modh +';}if(!this.modified){this.value=\'\';this.style.color=\'black\';this.modified=true;}" onBlur="if(this.value==\'\'){this.value=\'Ссылка\';this.style.color=\'gray\';this.modified=false;}">' +
                        '<br><a href=javascript:;><span onClick=window.ObjEditors['+me.id+'].moveImage('+img+',-1,'+elementId+');>Назад</span></a>' +
                        '&nbsp;<a href=javascript:;><span onClick=window.ObjEditors['+me.id+'].moveImage('+img+',1,'+elementId+');>Вперед</span></a>' +
                        '</div>';
                    }

                    
                    content += '<hr style="display: block; clear: left; visibility: hidden;">';

                    $('#img_id' + me.id + 'element' + elementId).empty();
                    $('#img_id' + me.id + 'element' + elementId).append(content);
                }
        });
    }

    me.moveImage = function(id, param, elementId) {
        $.ajax({
            type: "GET",
            url: "php/moveimg.php",
            cache: false,
            data: "id="+id+"&param="+param,
            success: function(msg){
                    me.updateImages(elementId);
                }
        });
    }

    me.delImage = function(id, elementId) {
        $.ajax({
            type: "GET",
            url: "php/delimg.php",
            cache: false,
            data: "id="+id,
            success: function(msg){
                    alert(msg);
                    me.updateImages(elementId);
                }
        });
    }

    me.save = function(onOk) {
        name = '';
        var query = "id=" + me.id;
        c = '';

        for (var i in me.properties.elements) {
            // datatype
            switch (me.properties.elements[i].type) {
                case '13':
                    c = '';
                    for ( var lst in me.properties.elements[i].list ) {
                        el = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id+'sub'+lst);
                        if (el.checked)
                        {
                            c = c + lst + ',';
                        }
                    }
                    if (c.length > 0) { c = c.substring(0, c.length - 1); }
                    break;

                case '1':
                    el = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id);
                    if (el.selectedIndex!=-1) { c = el.options[el.selectedIndex].value; } else { c = 0; }

                    if (me.properties.elements[i].isName == '1') {
                        name = el.options[el.selectedIndex].text;
                    }

                    break;

                case '2':
                    el = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id);
                    if (el != undefined) { c = el.value; }

                    c = replaceLinks(c);

                    break;

                case '3':
                case '10':
                case '14':
                    c = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id).value;

                    if (me.properties.elements[i].isName == '1') {
                        name = c;
                    }

                    break;

                case '15':
                    c = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id).checked ? 'on' : '';
                    
                    break;

                case '11':
                    var w = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id+'_w').value;
                    var h = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id+'_h').value;

                    if ((w == 0) && (h == 0)) { me.clearFlash(me.properties.elements[i].id); }

                    c = w + 'x' + h;

                    break;

                case '12':
                    var w = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id+'_w').value;
                    var h = document.getElementById('id'+me.id+'element'+me.properties.elements[i].id+'_h').value;

                    if ((w == 0) && (h == 0)) { me.clearVideo(me.properties.elements[i].id); }

                    c = w + 'x' + h;

                    break;

                case '4':
                    for (var k in me.properties.elements[i].images) {
                        j = me.properties.elements[i].images[k].id;

                        imgName='';
                        imgHref='';

                        // hackfix x 2
                        el = document.getElementById('img_name_id'+id+'element'+me.properties.elements[i].id+'img'+j);
                        if (el!=undefined) { if (el.modified) { imgName = el.value; } }

                        el = document.getElementById('img_href_id'+id+'element'+me.properties.elements[i].id+'img'+j);
                        if (el!=undefined) { if (el.modified) { imgHref = el.value; } }

                        //if (imgName != '')
                        {
                            query += '&el' + me.properties.elements[i].id + 'img' + j + 'name=' + encodeURIComponent(imgName);
                        }
                        //if (imgHref != '')
                        {
                            query += '&el' + me.properties.elements[i].id + 'img' + j + 'href=' + encodeURIComponent(imgHref);
                        }
                    }

                    break;
            }

            if (c != '') { query += '&el' + me.properties.elements[i].id + '=' + encodeURIComponent(c); }
        }

        $.ajax({
            type: "POST",
            url: "php/upost.php",
            cache: false,
            data: query,
            success: function(msg) {

                a = msg.split('::');
                if (a[0] == 'ok') {
                    $('#caption'+id).html(a[1]); // обновление name

                    me.changesCount = 0;

                    if (onOk != undefined) {
                        onOk();
                    } else {
                        alert('Сохранено! ');
                    }
                } else {
                    if (msg == 'access denied') {
                        alert('Доступ ограничен.');
                    } else {
                        alert('Error! ' + msg);
                    }
                }
            }
        });
    }

    _unconditionalClose = me.unconditionalClose;
    me.unconditionalClose = function() {
        window.ObjEditors[this.id] = null;
        if (window.editId != undefined) { if (window.editId == this.id) { window.editId = null; } }

        if (me.properties != undefined)
        for (var i in me.properties.elements) {
            // datatype
            switch (me.properties.elements[i].type) {
                case '2':
                    inst = tinyMCE.getInstanceById('id'+this.id+'element'+me.properties.elements[i].id);
                    if (inst != undefined) { tinyMCE.remove(inst); }

                    break;
            }
        }

        _unconditionalClose.call(this);
    }

    // === <Стандартный код для всех потомков ObjEditor> ===
    // инициализация переменных
    me.Init(id, classId, placeId);
    // поставить правильное свойство конструктора
    // (делаем вид, что объект создали мы, а не ObjEditor)
    me.constructor = arguments.callee;
    if(window.ObjEditors==undefined) { window.ObjEditors = new Array(); }
    window.ObjEditors[id] = me;
    return me;
    // === </Стандартный код для всех потомков ObjEditor> ===
}

// helper functions

function Get_Cookie( check_name ) {
    // first we'll split this cookie up into name/value pairs
    // note: document.cookie only returns name=value, not the other components
    a_all_cookies = document.cookie.split( ';' );
    a_temp_cookie = '';
    cookie_name = '';
    cookie_value = '';
    b_cookie_found = false; // set boolean t/f default f

    for ( i = 0; i < a_all_cookies.length; i++ ) {

        // now we'll split apart each name=value pair
        a_temp_cookie = a_all_cookies[i].split( '=' );


        // and trim left/right whitespace while we're at it
        cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

        // if the extracted name matches passed check_name
        if ( cookie_name == check_name ) {
            b_cookie_found = true;
            // we need to handle case where cookie has no value but exists (no = sign, that is):
            if ( a_temp_cookie.length > 1 ) {
                cookie_value = unescape( a_temp_cookie[1].replace(/^\s+|\s+$/g, '') );
            }
            // note that in cases where cookie is initialized but no value, null is returned
            return cookie_value;
            break;
        }
        a_temp_cookie = null;
        cookie_name = '';
    }
    if ( !b_cookie_found ) {
        return null;
    }
}

function undoEditorOpen() {
    restoreCaptionColor();
    delete window.editId;
    delete window.selectedLink;
}

function replaceLinks(c) {
    //
    // заменим ссылки вида
    // <a id="caption7" href="javascript:edit(7);">Ссылка</a>
    // на ссылки вида
    // <a id="caption7" href="/?p=7">Ссылка</a>
    //
    // подобные ссылки создаются в вызовах функции crEl
    //

    return c.replace(/javascript:edit\((\d+)\);/, "$1.html") ;
}
