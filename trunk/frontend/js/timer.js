var speed=1000

function dotimer(delta, object, limit, starta)
{
    today=new Date()
    sltsec=today.getSeconds()
    sltmin=today.getMinutes()
    slttim=today.getHours()
    slta=(sltsec) + 60 * (sltmin) + 3600 * (slttim)
    diff=slta - starta

    if ((diff > limit) && (limit > 0)) { testConnection(refreshCallBack) }

    tim=Math.floor(diff / 3600)
    min=Math.floor((diff / 3600 - tim) * 60)
    sek=Math.round((((diff / 3600 - tim) * 60) - min) * 60)

    var disptime = '';    
    if(tim<10)disptime='0'
    disptime+=tim + ':'
    if(min<10)disptime+='0'
    disptime+=min + ':'
    if(sek<10)disptime+='0'
    disptime+=sek

    document.getElementById(object).innerHTML = disptime

    window.setTimeout("dotimer('"+delta+"', '"+object+"', '"+limit+"', '"+starta+"')",speed)
}

function Timer(delta, object, limit)
{
    today=new Date()
    startsek=today.getSeconds()
    startmin=today.getMinutes()
    starttim=today.getHours()
    starta=(startsek) + 60 * (startmin) + 3600 * (starttim)
    starta = starta - delta
    dotimer(delta, object, limit, starta)
}
