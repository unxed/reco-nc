
function isUserOnline()
{
    $.ajax({
        type: "GET",
        url: "php/online.php",
        cache: false,
        data: "",
        success: function(msg){
            if (msg == 'redraw')
            {
                init();
            } else if (msg != 'ok')
            {
                alert('Session expired.');
                window.location = "php/login.php";
            }
        }
    });
}

// fixme: move 15000 to config somehow

setInterval('isUserOnline()', 15000);

