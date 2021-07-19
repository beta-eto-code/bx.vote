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

    /**
     * @param string $message
     */
    public function setError(string $message)
    {
        $GLOBALS["APPLICATION"]->ThrowException($message);
    }

    public function DoInstall()
    {
        $result = $this->installRequiredModules();
        if (!$result) {
            return false;
        }

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

    /**
     * @return bool
     */
    public function installRequiredModules(): bool
    {
        $isInstalled = ModuleManager::isModuleInstalled('bx.model');
        if ($isInstalled) {
            return true;
        }

        $modulePath = getLocalPath("modules/bx.model/install/index.php");
        if (!$modulePath) {
            $this->setError('Отсутствует модуль bx.model - https://github.com/beta-eto-code/bx.model');
            return false;
        }

        require_once $modulePath;
        $moduleInstaller = new bx_model();
        $resultInstall = (bool)$moduleInstaller->DoInstall();
        if (!$resultInstall) {
            $this->setError('Ошибка установки модуля bx.model');
        }

        return $resultInstall;
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
