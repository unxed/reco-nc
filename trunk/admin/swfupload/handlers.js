
function queueComplete()
{
    try {
        this.customSettings.contaner.updateImages(this.customSettings.elementId);
    } catch (ex) {
        this.debug(ex);
    }
}


/* This is an example of how to cancel all the files queued up.  It's made somewhat generic.  Just pass your SWFUpload
object in to this method and it loops through cancelling the uploads. */
function cancelQueue(instance) {
    document.getElementById(instance.customSettings.cancelButtonId).disabled = true;
    instance.stopUpload();
    var stats;
    
    do {
        stats = instance.getStats();
        instance.cancelUpload();
    } while (stats.files_queued !== 0);
    
}

/* **********************
   Event Handlers
   These are my custom event handlers to make my
   web application behave the way I went when SWFUpload
   completes different tasks.  These aren't part of the SWFUpload
   package.  They are part of my application.  Without these none
   of the actions SWFUpload makes will show up in my application.
   ********************** */
function fileDialogStart() {
    /* I don't need to do anything here */
}
function fileQueued(file) {
    try {
        // You might include code here that prevents the form from being submitted while the upload is in
        // progress.  Then you'll want to put code in the Queue Complete handler to "unblock" the form

        q = (this.getStats().files_queued-1);
        el = document.getElementById(this.customSettings.statusBar);
        if (q > 0) { el.innerHTML = 'Uploading, ' + q + ' file(s) qeued'; }
        else if (q == 0) { el.innerHTML = 'Uploading...'; }

        document.getElementById(this.customSettings.progressTarget).style.width = '0px';
    } catch (ex) {
        this.debug(ex);
    }

}

function fileQueueError(file, errorCode, message) {
    try {
        if (errorCode === SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
            alert("You have attempted to queue too many files.\n" + (message === 0 ? "You have reached the upload limit." : "You may select " + (message > 1 ? "up to " + message + " files." : "one file.")));
            return;
        }

        document.getElementById(this.customSettings.progressTarget).style.width = '0px';

        switch (errorCode) {
        case SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT:
            document.getElementById(this.customSettings.statusBar).innerHTML = "File is too big.";
            this.debug("Error Code: File too big, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.QUEUE_ERROR.ZERO_BYTE_FILE:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Cannot upload Zero Byte files.";
            this.debug("Error Code: Zero byte file, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.QUEUE_ERROR.INVALID_FILETYPE:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Invalid File Type.";
            this.debug("Error Code: Invalid File Type, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED:
            alert("You have selected too many files.  " +  (message > 1 ? "You may only add " +  message + " more files" : "You cannot add any more files."));
            break;
        default:
            if (file !== null) {
                document.getElementById(this.customSettings.statusBar).innerHTML = "Unhandled Error";
            }
            this.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        }
    } catch (ex) {
        this.debug(ex);
    }
}

function fileDialogComplete(numFilesSelected, numFilesQueued) {
    try {
        if (this.getStats().files_queued > 0) {
            document.getElementById(this.customSettings.cancelButtonId).disabled = false;
        }
        
        /* I want auto start and I can do that here */
        this.startUpload();
    } catch (ex)  {
        this.debug(ex);
    }
}

function uploadStart(file) {
    try {
        /* I don't want to do any file validation or anything,  I'll just update the UI and return true to indicate that the upload should start */

        document.getElementById(this.customSettings.progressTarget).style.width = '0px';
    }
    catch (ex) {
    }
    
    return true;
}

function uploadProgress(file, bytesLoaded, bytesTotal) {

    try {
        var percent = Math.ceil((bytesLoaded / bytesTotal) * 100);
        document.getElementById(this.customSettings.progressTarget).style.width=percent + '%';

    } catch (ex) {
        this.debug(ex);
    }
}

function uploadSuccess(file, serverData) {
    try {
        document.getElementById(this.customSettings.progressTarget).style.width = '100%';

    } catch (ex) {
        this.debug(ex);
    }
}

function uploadComplete(file) {
    try {
        /*  I want the next upload to continue automatically so I'll call startUpload here */

        q = (this.getStats().files_queued-1);
        el = document.getElementById(this.customSettings.statusBar);
        if (q > 0) { el.innerHTML = 'Uploading, ' + q + ' file(s) qeued'; }
        else if (q == 0) { el.innerHTML = 'Uploading...'; }
        else { el.innerHTML = '&nbsp;'; document.getElementById(this.customSettings.progressTarget).style.width = '0px'; }

        if (this.getStats().files_queued === 0) {
            document.getElementById(this.customSettings.cancelButtonId).disabled = true;
        } else {    
            this.startUpload();
        }
    } catch (ex) {
        this.debug(ex);
    }

}

function uploadError(file, errorCode, message) {
    try {
        document.getElementById(this.customSettings.progressTarget).style.width = '0px';

        switch (errorCode) {
        case SWFUpload.UPLOAD_ERROR.HTTP_ERROR:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Upload Error: " + message;
            this.debug("Error Code: HTTP Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.MISSING_UPLOAD_URL:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Configuration Error";
            this.debug("Error Code: No backend file, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_FAILED:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Upload Failed.";
            this.debug("Error Code: Upload Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.IO_ERROR:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Server (IO) Error";
            this.debug("Error Code: IO Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.SECURITY_ERROR:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Security Error";
            this.debug("Error Code: Security Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_LIMIT_EXCEEDED:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Upload limit exceeded.";
            this.debug("Error Code: Upload Limit Exceeded, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.SPECIFIED_FILE_ID_NOT_FOUND:
            document.getElementById(this.customSettings.statusBar).innerHTML = "File not found.";
            this.debug("Error Code: The file was not found, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.FILE_VALIDATION_FAILED:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Failed Validation.  Upload skipped.";
            this.debug("Error Code: File Validation Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.FILE_CANCELLED:
            if (this.getStats().files_queued === 0) {
                document.getElementById(this.customSettings.cancelButtonId).disabled = true;
            }
            document.getElementById(this.customSettings.statusBar).innerHTML = "Cancelled";
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_STOPPED:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Stopped";
            break;
        default:
            document.getElementById(this.customSettings.statusBar).innerHTML = "Unhandled Error: " + error_code;
            this.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        }
    } catch (ex) {
        this.debug(ex);
    }
}