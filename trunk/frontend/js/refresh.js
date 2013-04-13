var r_backup;

function getCookie(name) {
    var cookie = " " + document.cookie;
    var search = " " + name + "=";
    var setStr = null;
    var offset = 0;
    var end = 0;
    if (cookie.length > 0) {
        offset = cookie.indexOf(search);
        if (offset != -1) {
            offset += search.length;
            end = cookie.indexOf(";", offset)
            if (end == -1) {
                end = cookie.length;
            }
            setStr = unescape(cookie.substring(offset, end));
        }
    }
    return(setStr);
}

function testConnection(callBack)
{
//  resets form!
//    document.getElementsByTagName('body')[0].innerHTML +=
//        '<img id="testImage" style="display: none;" ' +
//        'src="img/t.gif?' + Math.random() + '" ' +
//        'onerror="testConnectionCallback(false);" ' +
//        'onload="testConnectionCallback(true);">';

    var element = document.getElementById('testImage');
    if (!(element === null)) { element.parentNode.removeChild(element); }

    var _body = document.getElementsByTagName('body')[0];
    var _img = document.createElement("img");
    _img.setAttribute("id","testImage");
    _img.setAttribute("width","1");
    _img.setAttribute("height","1");
    _img.setAttribute("src","img/t.gif?" + Math.random());
    _img.setAttribute("onerror","testConnectionCallback(false);");
    _img.setAttribute("onload","testConnectionCallback(true);");

    // 4IE
    _img.onreadystatechange = function()
    {
        var state = document.getElementById('testImage').readyState;

        if (state == 'complete')
        {
            testConnectionCallback(true);
        }

        // fixme: ловить onerror в IE мы пока не научились
    }

    _img.style.display = 'none';
    _body.appendChild(_img);

    testConnectionCallback = function(result)
    {
        callBack(result);

        var element = document.getElementById('testImage');
        if (!(element === null)) { element.parentNode.removeChild(element); }
    }    
}

function rChange()
{
    r_backup = document.getElementById('r').checked;
    rUpdate();
}

function rUpdate()
{
    var rt_min = 10;

    var r = document.getElementById('r').checked;
    var rt = document.getElementById('rt').value;

    var validChars = "0123456789";
    var newStr = "";
    var IsNumber = true;
    var Char;

    for (i = 0; i < rt.length && IsNumber == true; i++) 
    { 
        Char = rt.charAt(i); 
        if (validChars.indexOf(Char) != -1) 
        {
            newStr = newStr + Char;
        }
    }

    if (newStr > 2000) { newStr = 2000; }

    if (rt != newStr)
    {
        rt = newStr;
        document.getElementById('rt').value = rt;
    }

    if ((rt > 0) && (rt < rt_min))
    {
        rt = rt_min;
        document.getElementById('rt').value = rt;
    }

    document.getElementById('left').innerHTML = '';
    refreshCounter = 0;

    document.cookie = 'r=' + escape(r);
    document.cookie = 'rt=' + escape(rt);
}

function noRefresh(e)
{
    var keycode;
    if (window.event) keycode = window.event.keyCode;
    else if (e) keycode = e.which;

    // filter alphanumeric keys
    if ((keycode < 48) || (keycode > 90)) { return; }

    r_backup = r_backup || document.getElementById('r').checked;

    document.getElementById('r').checked = false;    
    rUpdate();
}

function resumeRefresh()
{
    document.getElementById('r').checked = r_backup;
    rUpdate();
}

function initRefresh()
{
    setTimeout("doRefresh();",1000);

    var r = getCookie('r');
    var rt = getCookie('rt');

    if (r == 'true')
    {
        document.getElementById('r').checked = true;
    }

    if (rt > 0)
    {
        document.getElementById('rt').value = rt;

        if (r == 'true')
        {
            document.getElementById('left').innerHTML = ' (' + rt + ')';
        }
    } else {
        document.getElementById('rt').value = 10;
    }
}

function refreshCallBack(result)
{
    if (result)
    {
        refreshCounter = 1;

        // location.reload(true);
        // Плохо, т.к. вместе со страницей перезагружаются все ресурсы.
        // Ниже - альтернативное решение.

        var rand = Math.floor(Math.random()*899999+100000); // 100 000 - 999 999
        var href = document.location.href;

        // Если в адресе есть #, уберем всё, что после него - оно нам будет только мешать.
        if (href.indexOf('#') != -1) { href = href.substr(0, href.indexOf('#')); }

        // Если последний символ адреса - '?', уберем его.
        if (href.indexOf('?') == href.length - 1) { href = href.substr(0, href.indexOf('?')); }

        // Если в адресе уже есть поле rand, уберем его.
        var idx = href.indexOf('rand=');
        if (idx != -1)
        {
            href = href.substr(0, idx - 1);
        }

        // Добавим к адресу новое поле rand.
        var separator;
        if (href.indexOf('?') == -1) { separator = '?'; } else { separator = '&'; }
        href = href + separator + 'rand=' + rand;

        window.location.replace(href);
    }
    else
    {
        // отключать обновление при отсутствии связи с сервером
        // document.getElementById('r').checked = false;    
        // rChange();
        refreshCounter = 0;
        document.getElementById('left').innerHTML = '[нет связи с сервером]';
    }
}

function doRefresh()
{
    var showCntr = false;

    if (window.refreshBlocked === undefined)
    {
        if (document.getElementById('r').checked)
        {
            var rt = document.getElementById('rt').value;
            if (rt > 0)
            {
                if (window.refreshCounter === undefined)
                {
                    refreshCounter = 1;
                    showCntr = true;
                } else {
                    refreshCounter++;

                    if (refreshCounter >= rt)
                    {
                        testConnection(refreshCallBack);
                    } else {
                        showCntr = true;
                    }
                }
            }
        }
    }

    if (showCntr)
    {
        var secLeft = rt - refreshCounter;
        document.getElementById('left').innerHTML = ' (' + secLeft + ')';
    }

    setTimeout("doRefresh();",1000);
}

function myCallBack(result)
{
    alert(result);
}
