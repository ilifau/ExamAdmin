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
     * Show a checkbox column
     * @var bool
     */
    protected $show_checkboxes = false;

	/**
	 * Link the user account
	 * @var bool
	 */
    protected $link_user = false;

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

        $this->setFormName('user_list');
        $this->setStyle('table', 'fullwidth');
        $this->addColumn('', '', '', true);
        $this->addColumn($this->lng->txt("name"), "name");
        $this->addColumn($this->lng->txt('login'), 'login');
        $this->addColumn($this->lng->txt('matriculation'), 'matriculation');
        $this->addColumn($this->lng->txt('email'), 'email');
        $this->addColumn($this->lng->txt('active'), 'active');
        $this->addColumn($this->lng->txt('time_limit_until'), 'time_limit_until');
        $this->addColumn($this->plugin->txt('password_change'), 'last_password_change');

        $this->setRowTemplate("tpl.il_exam_admin_user_list_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->enable('header');
        $this->disable('select_all');

        $this->setEnableNumInfo(true);
        $this->setExternalSegmentation(true);
	}


	public function setRowCommands($a_commands)
    {
        $this->row_commands = $a_commands;
        if (count( $this->row_commands))
        {
            $this->addColumn($this->lng->txt('actions'));
        }
    }

    public function setIdParameter($a_parameter)
    {
        $this->id_parameter = $a_parameter;
    }

    public function setShowCheckboxes($a_show)
    {
        $this->show_checkboxes = $a_show;
        $this->setSelectAllCheckbox($this->id_parameter);
    }

	/**
	 * @param bool $a_link
	 */
    public function setLinkUser($a_link)
	{
		$this->link_user = $a_link;
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

        if ($this->show_checkboxes)
        {
            $this->tpl->setVariable('ID_PARAMETER', $this->id_parameter);
            $this->tpl->setVariable('ID_VALUE', $a_set['usr_id']);
        }

		if ($this->link_user) {
			$this->ctrl->setParameterByClass("ilobjusergui", "ref_id", 7);
			$this->ctrl->setParameterByClass("ilobjusergui", "obj_id", $a_set['usr_id']);
			$link = $this->ctrl->getLinkTargetByClass(array("iladministrationgui", "ilobjusergui"), "view");
			$this->tpl->setVariable('LINK_USER', $link);
			$this->tpl->setVariable('LINKED_LOGIN', $a_set['login']);
		}
		else {
			$this->tpl->setVariable('LOGIN', $a_set['login']);
		}

		$this->tpl->setVariable('NAME', $a_set['name']);
        $this->tpl->setVariable('MATRICULATION', $a_set['matriculation']);
        $this->tpl->setVariable('E_MAIL', $a_set['email']);
        $this->tpl->setVariable('TIME_LIMIT', ilDatePresentation::formatDate(new ilDateTime($a_set['time_limit_until'], IL_CAL_UNIX)));
        $this->tpl->setVariable('CHANGE', ilDatePresentation::formatDate(new ilDateTime($a_set['last_password_change'], IL_CAL_UNIX)));
        $this->tpl->setVariable('ACTIVE', $a_set['active']);

        if (count($this->row_commands) > 0)
        {
            $this->tpl->setVariable('ACTIONS', $actions);
        }
    }
}