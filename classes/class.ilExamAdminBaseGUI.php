<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


/**
 * Base class for GUIs of the ExamAdmin plugin
 */
class ilExamAdminBaseGUI
{
    /** @var ilObjUser */
    protected $user;

	/** @var  ilAccessHandler $access */
	protected $access;

	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var  ilLanguage $lng */
	protected $lng;

	/** @var ilTabsGUI */
	protected $tabs;

	/** @var  ilToolbarGUI $toolbar */
	protected $toolbar;

	/** @var ilGlobalTemplate $tpl */
	protected $tpl;

	/** @var ilExamAdminPlugin $plugin */
	protected $plugin;

    /** @var ilExamAdminMainGUI */
	protected $mainGUI;


	/**
	 * ilExamAdminBaseGUI constructor
     * @param ilExamAdminMainGUI $mainGUI
	 */
	public function __construct($mainGUI = null)
	{
		global $DIC;

		$this->user = $DIC->user();
		$this->access = $DIC->access();
		$this->ctrl = $DIC->ctrl();
		$this->lng = $DIC->language();
		$this->tabs = $DIC->tabs();
		$this->toolbar = $DIC->toolbar();
		$this->tpl = $DIC['tpl'];

		if (isset($mainGUI)) {
		    $this->mainGUI = $mainGUI;
		    $this->plugin = $mainGUI->getPlugin();
        }
		else {
            $this->plugin = ilExamAdminPlugin::getInstance();
            $this->plugin->init();
        }
	}

    /**
     * Get the plugin object
     * @return ilExamAdminPlugin|null
     */
    public function getPlugin()
    {
        return $this->plugin;
    }


    /**
	 * Get the link target for a command using the ui plugin router
	 * @param string $a_cmd
	 * @return string
	 */
	protected function getLinkTarget($a_cmd = '', $a_anchor = '', $a_async = false)
	{
		return $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', get_class($this)), $a_cmd, $a_anchor, $a_async);
	}
}