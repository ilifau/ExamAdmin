<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * User interface hook class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExamAdminUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilAccessHandler $access */
    protected $access;

	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var ilTabsGUI $tabs */
	protected $tabs;

	/** @var  ilExamAdminPlugin $plugin_object */
	protected $plugin_object;

	/** @var int */
	protected $parent_ref_id;

    /** @var string  */
	protected $parent_type;

	/**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param array $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	public function modifyGUI($a_comp, $a_part, $a_par = array())
	{
	    $this->parent_ref_id = $_GET['ref_id'];
        $this->parent_type = ilObject::_lookupType($this->parent_ref_id, true);
        if (!$this->plugin_object->isAllowedType($this->parent_type))
        {
            return;
        }

		switch ($a_part)
		{
			//case 'tabs':
			case 'sub_tabs':

                // must be done here because ctrl and tabs are not initialized for all calls
                global $DIC;
                $this->ctrl = $DIC->ctrl();
                $this->tabs = $DIC->tabs();
                $this->access = $DIC->access();

                // exam admin page is shown in a group
                if (in_array($this->ctrl->getCmdClass(), array('ilexamadmincoursegui')))
                {
                    $this->restoreTabs('course');
                    $this->tabs->activateTab('examad');
                }
                // Course is shown
				elseif ($this->parent_type = 'crs')
				{
				    // course can be edited
                    if ($this->access->checkAccess('write','', $_GET['ref_id']))
                    {
                        // add exam admin tab
                        $this->ctrl->setParameterByClass('ilExamAdminCourseGUI', 'ref_id', $this->parent_ref_id);
                        $this->tabs->addTab('examad', $this->plugin_object->txt('exam_admin_tab'),
                            $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI','ilExamAdminCourseGUI')));

                        // save the situation when
                        if (in_array($this->ctrl->getCmdClass(), array('ilobjcoursegui', 'ilinfoscreengui', 'ilcoursemembershipgui', 'ilexportgui', 'ilpermissiongui')))
                        {
                            $this->saveTabs('course');
                        }
                    }
				}
				break;

			default:
				break;
		}
	}

	/**
	 * Save the tabs for reuse on the plugin pages
	 * @param string context for which the tabs should be saved
	 */
	protected function saveTabs($a_context)
	{
		$_SESSION['ExamAdmin'][$a_context]['TabTarget'] = $this->tabs->target;
	}

    /**
     * Save the sub tabs for reuse on the plugin pages
     * @param string context for which the tabs should be saved
     */
    protected function saveSubTabs($a_context)
    {
        $_SESSION['ExamAdmin'][$a_context]['TabSubTarget'] = $this->tabs->sub_target;
    }

    /**
	 * Restore the tabs for reuse on the plugin pages
	 * @param string context for which the tabs should be saved
	 */
	protected function restoreTabs($a_context)
	{
		// reuse the tabs that were saved from the parent gui
		if (isset($_SESSION['ExamAdmin'][$a_context]['TabTarget']))
		{
			$this->tabs->target = $_SESSION['ExamAdmin'][$a_context]['TabTarget'];
		}
	}

    /**
     * Restore the sub tabs for reuse on the plugin pages
     * @param string context for which the tabs should be saved
     */
    protected function restoreSubTabs($a_context)
    {
        // reuse the sub tabs that were saved from the parent gui
         if (isset($_SESSION['ExamAdmin'][$a_context]['TabSubTarget']))
        {
            $this->tabs->sub_target = $_SESSION['ExamAdmin'][$a_context]['TabSubTarget'];
        }
    }

    /**
     * Activate a tab with a given class in its link
     * @param string $a_class
     */
	protected function activateClassTab($a_class)
    {
        foreach ($this->tabs->target as $target)
        {
            if (strpos(strtolower($target['link']),strtolower($a_class)) !== false)
            {
                // this works when done in handler for the sub_tabs
                // because the tabs are rendered after the sub tabs
                $this->tabs->activateTab($target['id']);
            }
        }
    }

    /**
     * Check if function function is allowed
     * @return bool
     */
	protected function isAllowed()
    {
        $type = ilObject::_lookupType($_GET['ref_id'], true);
        return $this->plugin_object->isAllowedType($type);
    }

}
?>