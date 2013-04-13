
function display_c(start, id)
{
    if (window.start === undefined) { window.start = new Object(); }

    window.start[id] = parseFloat(start);
    var end = 0 // change this to stop the counter at a higher value
    var refresh=1000; // Refresh rate in milli seconds

    if(window.start[id] >= end )
    {
        mytime=setTimeout("display_ct('" + id + "')",refresh)

    } else {
        document.getElementById(id).innerHTML = '';
        testConnection(refreshCallBack);
    }
}

function display_ct(id)
{
    // Calculate the number of days left
    var days=Math.floor(window.start[id] / 86400); 
    // After deducting the days calculate the number of hours left
    var hours = Math.floor((window.start[id] - (days * 86400 ))/3600)
    // After days and hours , how many minutes are left 
    var minutes = Math.floor((window.start[id] - (days * 86400 ) - (hours *3600 ))/60)
    // Finally how many seconds left after removing days, hours and minutes. 
    var secs = Math.floor((window.start[id] - (days * 86400 ) - (hours *3600 ) - (minutes*60)))

    var x = '';
    
    // format hours, minutes, secs
    if (hours.toString().length == 1) { hours = '0' + hours; }
    if (minutes.toString().length == 1) { minutes = '0' + minutes; }
    if (secs.toString().length == 1) { secs = '0' + secs; }

    // hackfix: ignoring days
    if (hours > 0) { x = x + hours + ':'; }
    x = x + minutes + ':';
    x = x + secs;

    document.getElementById(id).innerHTML = x;
    window.start[id] = window.start[id] - 1;

    tt=display_c(window.start[id], id);
}
