<?php

IncludeModuleLangFile(__FILE__);
use \Bitrix\Main\ModuleManager;

class bx_vote extends CModule
{
    public $MODULE_ID = "bx.vote";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $errors;

    public function __construct()
    {
        $this->MODULE_VERSION = "0.1.0";
        $this->MODULE_VERSION_DATE = "2021-05-25 09:49:27";
        $this->MODULE_NAME = "Опросы (альтернативный API)";
        $this->MODULE_DESCRIPTION = "Опросы (альтернативный API)";
    }

    public function DoInstall()
    {
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        ModuleManager::RegisterModule($this->MODULE_ID);
        return true;
    }

    public function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        ModuleManager::UnRegisterModule($this->MODULE_ID);
        return true;
    }

    public function InstallDB()
    {
        return true;
    }

    public function UnInstallDB()
    {
        return true;
    }

    public function InstallEvents()
    {
        return true;
    }

    public function UnInstallEvents()
    {
        return true;
    }

    public function InstallFiles()
    {
        return true;
    }

    public function UnInstallFiles()
    {
        return true;
    }
}
