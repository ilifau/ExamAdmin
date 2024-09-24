<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExamAdmin configuration user interface class
 *
 * @ilCtrl_Calls: ilExamAdminConfigGUI: ilPropertyFormGUI
*  @ilCtrl_IsCalledBy ilExamAdminConfigGUI: ilObjComponentSettingsGUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExamAdminConfigGUI extends ilPluginConfigGUI
{
	/** @var ilExamAdminPlugin $plugin */
	protected $plugin;

	/** @var ilExamAdminConfig $config */
	protected $config;

	/** @var ilTabsGUI $tabs */
    protected $tabs;

    /** @var ilCtrl $ctrl */
    protected $ctrl;

    /** @var ilLanguage $lng */
	protected $lng;

    /** @var ilTemplate $lng */
	protected $tpl;

    /** @var  ilToolbarGUI $toolbar */
    protected $toolbar;

	public function __construct()
	{
		global $DIC;

        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->toolbar = $DIC->toolbar();
        $this->tpl = $DIC->ui()->mainTemplate();
	}

    /**
	 * Handles all commands, default is "configure"
     * @throws Exception
	 */
	public function performCommand(string $cmd): void
	{
        global $DIC;

        // this can't be in constructor
        $this->plugin = $this->getPluginObject();
        $this->config = $this->plugin->getConfig();

        $this->tabs->addTab('basic', $this->plugin->txt('basic_configuration'), $this->ctrl->getLinkTarget($this, 'configure'));
        $this->setToolbar();

        switch ($DIC->ctrl()->getNextClass())
        {
            case 'ilpropertyformgui':
                switch ($_GET['config'])
                {
                    case 'basic':
                        $DIC->ctrl()->forwardCommand($this->initBasicConfigurationForm());
                        break;
                }

                break;

            default:
                switch ($cmd)
                {
                    case "configure":
                    case "saveBasicSettings":
                    case "installCourses":
                    case "syncUserData":
                        $this->tabs->activateTab('basic');
                        $this->$cmd();
                        break;
                }
        }
	}

	/**
	 * Show base configuration screen
	 */
	protected function configure()
	{
		$form = $this->initBasicConfigurationForm();
		$this->tpl->setContent($form->getHTML());
	}

    /**
     * Set the toolbar
     */
    protected function setToolbar()
    {
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));

        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'installCourses'));
        $button->setCaption($this->plugin->txt('install_courses'), false);
        $this->toolbar->addButtonInstance($button);

        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'syncUserData'));
        $button->setCaption($this->plugin->txt('synchronizeUsers'), false);
        $this->toolbar->addButtonInstance($button);
    }


    /**
	 * Initialize the configuration form
	 * @return ilPropertyFormGUI form object
	 */
	protected function initBasicConfigurationForm()
	{
		$form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('config_base'));
        $form->setDescription($this->plugin->txt('config_base_info'));
		$form->setFormAction($this->ctrl->getFormAction($this));

        foreach($this->config->getParams() as $param)
        {
            $param->setValue($this->config->get($param->name));
            $form->addItem($param->getFormItem());
        }

		$form->addCommandButton("saveBasicSettings", $this->lng->txt("save"));
		return $form;
	}

	/**
	 * Save the basic settings
	 */
	protected function saveBasicSettings()
	{
        global $DIC;

		$form = $this->initBasicConfigurationForm();
		if ($form->checkInput())
		{
		    $form->setValuesByPost();
		    foreach ($this->config->getParams() as $param)
            {
                $this->config->set($param->name, $param->getFormValue($form));
            }
            $this->config->write();

            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->lng->txt('settings_saved'), true);
			$this->ctrl->redirect($this, 'configure');
		}
		else
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHtml());
		}
	}

    /**
     * Install Courses
     */
	protected function installCourses()
    {
        global $DIC;
        $this->plugin->init();
        $handler = new ilExamAdminCronHandler($this->plugin);
        $courses = $handler->installCourses();

        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', implode('<br />', $courses), true);
        $this->configure();
    }

    /**
     * Sync User data
     */
    protected function syncUserData()
    {
        global $DIC;
        $this->plugin->init();
        $handler = new ilExamAdminCronHandler($this->plugin);
        $count = $handler->syncUserData();

        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', sprintf($this->plugin->txt('x_users_synchronized'), $count), false);
        $this->configure();
    }
}

?>