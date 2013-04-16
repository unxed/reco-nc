
// partially from http://javascript.ru/ui/tree

// ------------------------------
// tree_toggle.js
// ------------------------------

function tree_toggle(event) {
    event = event || window.event
    var clickedElem = event.target || event.srcElement
 
    if (!hasClass(clickedElem, 'Expand')) {
        return // клик не там
    }
 
    // Node, на который кликнули
    var node = clickedElem.parentNode
    if (hasClass(node, 'ExpandLeaf')) {
        return // клик на листе
    }
 
    // определить новый класс для узла
    var newClass = hasClass(node, 'ExpandOpen') ? 'ExpandClosed' : 'ExpandOpen'
    // заменить текущий класс на newClass
    // регексп находит отдельно стоящий open|close и меняет на newClass
    var re =  /(^|\s)(ExpandOpen|ExpandClosed)(\s|$)/
    node.className = node.className.replace(re, '$1'+newClass+'$3')
}
 
 
function hasClass(elem, className) {
    return new RegExp("(^|\\s)"+className+"(\\s|$)").test(elem.className)
}


// ------------------------------
// tree_tools.js
// ------------------------------

// kill element
function rmEl(id) {

    thisItem = $("#item"+id);

    itemParentId = $("#item"+id).parent().parent().attr('id').substring(4);

    if (itemLastChildId = $("#item"+itemParentId+" > ul > li:last-child").attr('id'))
        itemLastChildId = itemLastChildId.substring(4);
    else
        itemLastChildId = id;

    thisItem.remove();

    if (itemLastChildId == id) {
        itemLastChild = $("#item"+itemParentId+" > ul > li:last-child");
        itemLastChild.addClass("IsLast");
    }

    if (!($("#item"+itemParentId+" > ul > li").attr('id'))) {
        $("#item"+itemParentId+" > ul").remove;
        $("#item"+itemParentId).removeClass('ExpandOpen');
        $("#item"+itemParentId).removeClass('ExpandClose');
        $("#item"+itemParentId).addClass('ExpandLeaf');
    }
}

// create element
function crEl(id,newId,newContent,openFlag) {

    e = $("#item"+id);
    e_ul = $("#item"+id+" > ul");
    ulNew = (e_ul.length==0);
    if (ulNew) {
        e.append("<ul class=\"Container\"></ul>");
        e_ul = $("#item"+id+" > ul");
    }

    e_li = $("#item"+id+" > ul > li:last-child");

    code = "<li id=\"item" + newId + "\"><div class=\"Expand\"></div><div class=\"Content\">" + newContent + "</div></li>";
    e_ul.append(code);

    e_liNew = $("#item"+id+" > ul > li:last-child");

    liNew = (e_li.length==0);
    if (liNew) {
        e_liNew.addClass("ExpandLeaf");
    }

    if (e.hasClass("ExpandLeaf")) {       

        e.removeClass("ExpandLeaf");

        if (openFlag) {
            e.removeClass("ExpandClosed");
            e.addClass("ExpandOpen");
        } else {
            e.removeClass("ExpandOpen");
            e.addClass("ExpandClosed");
        }
    }

    e_li.removeClass("IsLast");
    e_liNew.addClass("ExpandLeaf").addClass("Node").addClass("IsLast");

    if ($("#item"+id+".Root").length>0) {
        e_liNew.addClass("IsRoot");
    }
}


// ------------------------------
// treeview.js
// ------------------------------

function getCurrentEditor() {
    if (window.editId != undefined) {
        if (window.ObjEditors != undefined) {
            if (window.ObjEditors[window.editId] != undefined) {

                return window.ObjEditors[window.editId];
            }
        }
    }

    return 0;
}

function edit(id) {
    if (ObjEditor(id, 'editbox') != false) {

        current = $('#caption' + id);
        if (current) {
            current.attr('data-original-color', current.css('color'));
            current.css({'color': 'red'});
        }

        if (id!=window.selectedLink) {
            restoreCaptionColor();
        }

        window.selectedLink = id;
    }
}

function restoreCaptionColor() {
    if (window.selectedLink) {
        el = $('#caption' + window.selectedLink);

        if (el) {
            el.css({'color': el.attr('data-original-color')});
        }
    }
}

// a - id
// b - название
// c - глубина вложенности
// d - разрешено ли удаление
// e - выводить сереньким (вроде бы для r/o веток)

function getElCode(a,b,c,d,e) {

    // calculate size and weight depending on nesting level
    size = 13 - c; 
    if (size < 9) { size = 9; }
    style = 'style="font-size:' + size + 'pt;';
    if (c%2) { style += ' font-weight: bold; '; }
    if (e==0) { style += ' color: #aaa; '; }
    style += '"';

    if (a == 1) {
        out = '<span id=treeEl' + a + '>' +
        '<a href="javascript:edit(' + a + ');" id="caption' + a + '" ' + style + ' >' + b + '</a></span>';
    } else {
        out = '<div style="display:inline;" id=treeEl' + a +
        ' onMouseOver="$(this).find(\'.object-actions\').show();" ' +
        ' onMouseOut="$(this).find(\'.object-actions\').hide();" > ' +
        '<a href="javascript:edit(' + a + ');" id="caption' + a + '" ' + style + ' ' +
        '>' + b + '</a>' +
        '<span class="object-actions" style="display: none;" id=treeUD'+a+'>' +
        '&nbsp;<a title="Вверх" href=javascript:moveEl(' + a + ',\'-1\');>&uarr;</a>' +
        '&nbsp;<a title="Вниз" href=javascript:moveEl(' + a + ',\'1\');>&darr;</a>';

        if (d == 1) {
            out += '&nbsp;<a title="Удалить" href="javascript:if(confirm(\'Удалить объект?\')){rmElAjax(' + a + ');}" style=color:red;>X</a>';
        }

        out += '&nbsp;<a title="Добавить" href="javascript:addObject('+a+');" style=color:green;>+</a>';

        out += '</span></div>';
    }

    return out;
}

function UnCheckAllOthers(current) {

    for (var i=0; i<current.form.elements.length; i++) {

        el = current.form.elements[i];

        if ((el.checked) && (el != current)) {
            el.checked = false;
        }
    }
}

function resetAddObjectMode() {

    var classSelection = $('#classSelection');

    $('#classSelection').remove();
    $('#classSelectionContainer').append(classSelection);
    $('#classSelection').hide();
}

function rmElAjax(id) {

    resetAddObjectMode();

    // запросим список удаляемых элементов (со всеми вложенными), чтобы проверить, не редактируем ли мы сейчас один из них
    $.ajax({
        type: "GET",
        url: "php/lstree.php",
        cache: false,
        data: "ids="+id,
        success: function(msg){

            b = msg.split(':');
            a = b[0].split(',');

            if (b[2] == 1) {
                alert('Удаляемый объект, или одна из вложенных в него, редактируется другим пользователем. Удаление невозможно.');
                return;
            }

            if (b[1] > 0) {
                if (!confirm('Удаление этого объекта, включая все вложенные, приведет к удалению ' + b[1] + ' свазанных изображений. Вы уверены?')) {
                    return;
                }
            }

            result = true;

            for ( var i in a ) {
                if (window.editId != undefined) {
                    if (window.editId == a[i]) {
                        if (window.ObjEditors != undefined) {
                            if (window.ObjEditors[window.editId] != undefined) {
                                result = window.ObjEditors[window.editId].close();
                            }
                        }
                    }
                }
            }

            // если не редактируем, или на предложение "закрыть" пользователь ответил утвердительно, удаляем
            if (result) {
                $.ajax({
                    type: "GET",
                    url: "php/remove.php",
                    cache: false,
                    data: "ids="+id,
                    success: function(msg){

                        if (msg == 'access denied') { alert('Доступ ограничен.'); return; }

                        a = msg.split(',');

                        for ( var i in a )
                        {
                            if (a[i] != '')
                            {
                                rmEl(a[i]);
                            }
                        }
                    }
                });
            }
        }
    });
}


function moveEl(id, param) {
    $.ajax({
        type: "GET",
        url: "php/move.php",
        cache: false,
        data: "id="+id+"&param="+param,
        success: function(msg){
                if (param == -1) {
                    // move up
                    if ($('#item' + id).hasClass('IsLast')) { $('#item' + id).removeClass('IsLast').prev().addClass('IsLast'); }
                    $('#item' + id).after($('#item' + id).prev());
                }
                if (param == 1) {
                    // move down
                    if ($('#item' + id).next().hasClass('IsLast')) { $('#item' + id).addClass('IsLast').next().removeClass('IsLast'); }
                    $('#item' + id).before($('#item' + id).next());
                }
            }
        });
}

function addObject(parent_id) {

    var classSelection = $('#classSelection');

    $('#classSelection').remove();
    $('#treeEl' + parent_id).append(classSelection);
    $('#classSelection').show();

    $('#addNewObject').one('click', function(e) {

        // hide arrows, del, add
        $('#treeUD' + parent_id).hide();

        var classId = $('#classList').val();
        resetAddObjectMode();

        $.ajax({
            type: "GET",
            url: "php/new.php",
            cache: false,
            data: "parent=" + parent_id + "&class=" + classId,
            success: function(msg) {
                if (msg == 'invalid_parent_fail') {
                    alert('В этот объект нельзя добавить вложенный объект данного типа.');
                } else if (msg == 'access denied') {
                    alert('Доступ ограничен.');
                } else {
                    a = msg.split(':');
                    crEl(parent_id, a[0], getElCode(a[0], a[2], a[1], 1, 1), true);
                }
            }
        });

        return;
    });
}

function init() {
    $('#treeContainer').html('<div class=Root id=item1 onclick="tree_toggle(arguments[0])">' + getElCode(1,'Все объекты',1,0,1) + '</div>');

    // hackfix
    $('#treeUD1').hide();

    list(1, false);
}

function list(id, op) {

    $.ajax({
        type: "GET",
        url: "php/list.php?id="+id,
        cache: false,
        success: function(msg){

                a = msg.split('\n');
                for ( var i in a ) {
                    b = a[i].split('::');
                    if (b[1] != undefined) {
                        crEl(b[0], b[1], getElCode(b[1], b[2], b[3], b[4], b[5]), op);
                    }
                }
            }
    });
}
