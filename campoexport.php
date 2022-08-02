<?php
chdir('../../../../../../../');

require_once('./Services/Context/classes/class.ilContext.php');
ilContext::init(ilContext::CONTEXT_WEB);

require_once("./Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

require_once (__DIR__ . '/classes/class.ilExamAdminCampoExportHandler.php');
$campoExport = new ilExamAdminCampoExportHandler();

$campoExport->handleRequest();