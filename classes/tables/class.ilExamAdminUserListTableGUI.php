<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilExamAdminUserListTableGUI extends ilTable2GUI
{
	/**
	 * @var ilExamAdminGroupGUI $parent_obj
	 */
	protected $parent_obj;

	/**
	 * @var string $parent_cmd
	 */
	protected $parent_cmd;

	/**
	 * @var ilExamAdminPlugin|null
	 */
	protected $plugin;


    /**
     * @var string[]
     */
	protected $row_commands = [];

    /**
     * Get or post parameter to be used for user ids
     * @var string
     */
	protected $id_parameter = 'usr_id';

	/**
	 * Constructor.
	 * @param object	$a_parent_obj
	 * @param string 	$a_parent_cmd
	 */
	public function __construct($a_parent_obj, $a_parent_cmd)
	{
		global $lng, $ilCtrl;

		$this->lng = $lng;
		$this->ctrl = $ilCtrl;
		$this->parent_obj = $a_parent_obj;
		$this->parent_cmd = $a_parent_cmd;
		$this->plugin = $a_parent_obj->getPlugin();

        $this->setId('ilExamAdminUserListTableGUI');
        $this->setPrefix('ilExamAdminUserList');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setFormName('user_overview');
        $this->setTitle($this->plugin->txt('user_overview'));
        $this->setStyle('table', 'fullwidth');
        $this->addColumn($this->lng->txt("name"), "name");
        $this->addColumn($this->lng->txt('login'), 'login');
        $this->addColumn($this->lng->txt('matriculation'), 'matriculation');
        $this->addColumn($this->lng->txt('email'), 'email');
        $this->addColumn($this->lng->txt('active'), 'active');
        $this->addColumn($this->lng->txt('time_limit_until'), 'time_limit_until');
        $this->addColumn($this->plugin->txt('password_change'), 'last_password_change');
        $this->addColumn($this->lng->txt('actions'));

        $this->setRowTemplate("tpl.il_exam_admin_user_list_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->enable('header');
        $this->disable('select_all');

        $this->setEnableNumInfo(false);
        $this->setExternalSegmentation(true);
	}


	public function setRowCommands($a_commands)
    {
        $this->row_commands = $a_commands;
    }

    public function setIdParameter($a_parameter)
    {
        $this->id_parameter = $a_parameter;
    }

    protected function fillRow($a_set)
    {
        $this->ctrl->setParameter($this->parent_obj, $this->id_parameter, $a_set['usr_id']);
        if (count($this->row_commands) > 1)
        {
            // prepare action menu
            $list = new ilAdvancedSelectionListGUI();
            $list->setSelectionHeaderClass('small');
            $list->setItemLinkClass('small');
            $list->setId('actl_'. rand(0, 999999));
            $list->setListTitle($this->lng->txt('actions'));

            foreach ($this->row_commands as $command)
            {
                $list->addItem($this->plugin->txt($command), '', $this->ctrl->getLinkTarget($this->parent_obj, $command));
            }

            $actions = $list->getHTML();
        }
        elseif (count($this->row_commands) == 1)
        {
            $command = $this->row_commands[0];
            $button = ilLinkButton::getInstance();
            $button->setCaption($this->plugin->txt($command), false);
            $button->setUrl( $this->ctrl->getLinkTarget($this->parent_obj, $command));
            $actions = $button->getToolbarHTML();
        }

        $this->tpl->setVariable('NAME', $a_set['name']);
        $this->tpl->setVariable('LOGIN', $a_set['login']);
        $this->tpl->setVariable('MATRICULATION', $a_set['matriculation']);
        $this->tpl->setVariable('E_MAIL', $a_set['email']);
        $this->tpl->setVariable('TIME_LIMIT', ilDatePresentation::formatDate(new ilDateTime($a_set['time_limit_until'], IL_CAL_UNIX)));
        $this->tpl->setVariable('CHANGE', ilDatePresentation::formatDate(new ilDateTime($a_set['password_change'], IL_CAL_UNIX)));
        $this->tpl->setVariable('ACTIVE', $a_set['active']);
        $this->tpl->setVariable('ACTIONS', $actions);
    }
}