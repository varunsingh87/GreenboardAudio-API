<?php

namespace Flytrap\DBHandlers;

use Flytrap\Security\NumberAlphaIdConverter;
use Flytrap\Computable;
use Flytrap\EndpointResponse;

class CreateAudioOperation implements Computable
{
    protected $alphaIdConversion;
    protected UserChecker $dbHandler;
    protected string $fileName;

    public function __construct($dbHandler, $fileName)
    {
        $this->alphaIdConversion = new NumberAlphaIdConverter(10);
        $this->dbHandler = $dbHandler;
        $this->fileName = $fileName;
    }

    public function compute()
    {
        foreach ($_FILES as $file) {
            if (!$this->saveFile($file))
                $this->identifyError($file);

            // Delete the temporary file if it still exists
            if (file_exists($file['tmp_name']) && is_file($file['tmp_name']))
                unlink($file['tmp_name']);
        }
    }

    function saveAudio($file, $alphaId)
    {
        if (move_uploaded_file($file['tmp_name'], "../../audio_uploads/" . $alphaId)) {
            return EndpointResponse::outputSuccessWithoutData();
        } else { // File not saved to the directory
            return EndpointResponse::outputGenericError();
        }
    }

    function enterFile($fileName, $fileExtension)
    {
        $fileAlphaId = $this->alphaIdConversion->generateId();
        $q = "INSERT INTO audio_files (alpha_id, file_name, file_extension, user_id) VALUES ('$fileAlphaId', '$fileName', '$fileExtension', {$this->dbHandler->userId})";
        $this->dbHandler->executeQuery($q);

        if ($this->dbHandler->lastQueryWasSuccessful()) {
            $q = "SELECT * FROM audio_files WHERE alpha_id = '$fileAlphaId'";
            $r = $this->dbHandler->executeQuery($q);
            $audioFileData = mysqli_fetch_assoc($r);

            if ($this->saveAudio($audioFileData, $fileAlphaId)) {
                return EndpointResponse::outputSuccessWithData($audioFileData);
            }
        } else { // File credentials not saved on the database
            return EndpointResponse::outputSpecificErrorMessage(500, "The file details were not saved due to a system error", $q);
        }
    }

    function identifyError($file)
    {
        if (isset($file['error']) && $file['error'] > 0) {

            // Print a message based upon the error
            switch ($file['error']) {
                case 1:
                    return EndpointResponse::outputSpecificErrorMessage(422, "file exceeds the max file size");
                    break;
                case 2:
                    return EndpointResponse::outputSpecificErrorMessage(422, "file exceeds the max file size");
                    break;
                case 3:
                    return EndpointResponse::outputSpecificErrorMessage(500, "the file was only partially uploaded");
                    break;
                case 4:
                    return EndpointResponse::outputSpecificErrorMessage(404, "no file was uploaded");
                    break;
                case 6:
                    return EndpointResponse::outputSpecificErrorMessage(500, "no temporary folder was available");
                    break;
                case 7:
                    return EndpointResponse::outputGenericError('', NULL, "unable to write to the disk");
                    break;
                case 8:
                    return EndpointResponse::outputSpecificErrorMessage(500, "file upload stopped");
                    break;
                default:
                    return EndpointResponse::outputGenericError();
                    break;
            }
        } else {
            return EndpointResponse::outputGenericError();
        }
    }

    function saveFile($file)
    {
        $allowed = ['audio/wav', 'audio/x-wav', 'audio/mp3', 'audio/mpeg'];
        if (in_array($file['type'], $allowed)) {
            return $this->enterFile(substr($file, strrpos($file, ".")), substr($file, -strrpos($file, ".")));
        } else {
            return EndpointResponse::outputSpecificErrorMessage(415, "file type is not allowed");
        }
    }
}
