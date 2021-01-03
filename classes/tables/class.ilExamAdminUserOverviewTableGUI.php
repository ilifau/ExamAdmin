<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilExamAdminUserOverviewTableGUI extends ilTable2GUI
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

        $this->setId('ilExamAdminUserOverviewTableGUI');
        $this->setPrefix('ilExamAdminUserOverview');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setFormName('user_overview');
        $this->setTitle($this->plugin->txt('user_overview'));
        $this->setStyle('table', 'fullwidth');
        $this->addColumn($this->plugin->txt("category"));
        $this->addColumn($this->plugin->txt("active"));
        $this->addColumn($this->plugin->txt('inactive'));
        if ($this->plugin->hasAdminAccess()) {
            $this->addColumn($this->plugin->txt('password_change'));
        }
        $this->addColumn($this->plugin->txt('actions'));

        $this->setRowTemplate("tpl.il_exam_admin_user_overview_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->enable('header');
        $this->disable('select_all');

        $this->setEnableNumInfo(false);
        $this->setExternalSegmentation(true);
	}


    protected function fillRow($a_set)
    {
        $this->ctrl->setParameter($this->parent_obj, 'category', $a_set['category']);

        // prepare action menu
        $list = new ilAdvancedSelectionListGUI();
        $list->setSelectionHeaderClass('small');
        $list->setItemLinkClass('small');
        $list->setId('actl_'. rand(0, 999999));
        $list->setListTitle($this->lng->txt('actions'));
        foreach ($a_set['commands'] as $command)
        {
            $list->addItem($this->plugin->txt($command), '', $this->ctrl->getLinkTarget($this->parent_obj, $command));
        }

        $title = $this->plugin->txt($a_set['category']);
        $info = $this->plugin->txt($a_set['category']. '_info');
        $link = $this->ctrl->getLinkTarget($this->parent_obj, 'listUsers');
        $title = '<a href="' . $link . '">' .$title . '</a><p class="small">' . $info . '</p>';

        $this->tpl->setVariable('CATEGORY', $title);
        $this->tpl->setVariable('ACTIVE', empty($a_set['active']) ? '' : $a_set['active']);
        $this->tpl->setVariable('INACTIVE', empty($a_set['inactive']) ? '' : $a_set['inactive']);
        if ($this->plugin->hasAdminAccess()) {
            $this->tpl->setVariable('CHANGE', empty($a_set['last_password_change']) ? '&nbsp;' : ilDatePresentation::formatDate(new ilDateTime($a_set['last_password_change'], IL_CAL_UNIX)) . '&nbsp;');
        }
        $this->tpl->setVariable('ACTIONS', $list->getHTML());
    }
}