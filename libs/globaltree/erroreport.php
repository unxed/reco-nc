<?

ini_set('display_errors', 0);
register_shutdown_function('checkError');

ddd();

function checkError()
{
    $isError = false;
    if ($error = error_get_last())
    {
        switch($error['type'])
        {
                    case E_ERROR:
                    case E_CORE_ERROR:
                    case E_COMPILE_ERROR:
                    case E_USER_ERROR:
                        $isError = true;
                        break;
        }
    }

    if ($isError)
    {
        sendError("Fatal error: {$error['message']}");
    }
}

function sendError($msg)
{
    header("HTTP/1.1 500 Internal server error");
    echo $msg; // fixme: it seems this $msg goes nowhere, but it should rise an alert
}
