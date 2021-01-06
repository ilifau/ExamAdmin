<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExamAdmin plugin config class
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilExamAdminConfig
{
	/**
	 * @var ilExamAdminParam[]	$params		parameters: 	name => ilExamAdminParam
	 */
	protected $params = array();

	/**
	 * Constructor.
	 * @param ilPlugin|string $a_plugin_object
	 */
	public function __construct($a_plugin_object = "")
	{
		$this->plugin = $a_plugin_object;
		$this->plugin->includeClass('param/class.ilExamAdminParam.php');

		/** @var ilExamAdminParam[] $params */
		$params = array();

        $params[] = ilExamAdminParam::_create(
            'global_admin_role',
            $this->plugin->txt('cat_global_admin_role'),
            $this->plugin->txt('cat_global_admin_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        $params[] = ilExamAdminParam::_create(
            'global_lecturer_role',
            $this->plugin->txt('cat_global_lecturer_role'),
            $this->plugin->txt('cat_global_lecturer_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        $params[] = ilExamAdminParam::_create(
            'global_participant_role',
            $this->plugin->txt('cat_global_participant_role'),
            $this->plugin->txt('cat_global_participant_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        // format
        $params[] = ilExamAdminParam::_create(
            'exam_format',
            $this->plugin->txt('exam_format'),
            $this->plugin->txt('exam_format_info'),
            ilExamAdminParam::TYPE_MULTISELECT,
            'presence',
            [
                'presence' =>  $this->plugin->txt('exam_format_presence'),
                'open' =>  $this->plugin->txt('exam_format_open'),
                'monitored' => $this->plugin->txt('exam_format_monitored'),
            ]
        );

        // semester
        for ($y = 2020; $y <= 2030; $y++) {
            $options[$y . 's'] = $this->plugin->txt('summer_term') . ' ' . $y;
            $options[$y . 'w'] = $this->plugin->txt('winter_term') . ' ' . $y . '/' . ($y + 1);
        }
        $params[] = ilExamAdminParam::_create(
            'semester',
            $this->plugin->txt('semester'),
            $this->plugin->txt('semester_info'),
            ilExamAdminParam::TYPE_SELECT, '2020w',
            $options
        );

        // test data
        $params[] = ilExamAdminParam::_create(
            'testdata',
            $this->plugin->txt('testdata'),
            $this->plugin->txt('testdata_info'),
            ilExamAdminParam::TYPE_BOOLEAN,
            0
        );

        // master course
        $params[] = ilExamAdminParam::_create(
            'master_course',
            $this->plugin->txt('master_course'),
            $this->plugin->txt('master_course_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );

        // parent category
        $params[] = ilExamAdminParam::_create(
            'parent_category',
            $this->plugin->txt('parent_category'),
            $this->plugin->txt('parent_category_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );


        foreach ($params as $param)
        {
            $this->params[$param->name] = $param;
        }
        $this->read();
	}

    /**
     * Get the array of all parameters
     * @return ilExamAdminParam[]
     */
	public function getParams()
    {
        return $this->params;
    }

    /**
     * Get the value of a named parameter
     * @param $name
     * @return  mixed
     */
	public function get($name)
    {
        if (!isset($this->params[$name]))
        {
            return null;
        }
        else
        {
            return $this->params[$name]->value;
        }
    }

    /**
     * Set the value of the named parameter
     * @param string $name
     * @param mixed $value
     *
     */
    public function set($name, $value = null)
    {
        $param = $this->params[$name];

        if (isset($param))
        {
            $param->setValue($value);
        }
    }


    /**
     * Read the configuration from the database
     */
	public function read()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "SELECT * FROM examad_config";
        $res = $ilDB->query($query);
        while($row = $ilDB->fetchAssoc($res))
        {
            $this->set($row['param_name'], $row['param_value']);
        }
    }

    /**
     * Write the configuration to the database
     */
    public function write()
    {
        global $DIC;
        $ilDB = $DIC->database();

        foreach ($this->params as $param)
        {
            $ilDB->replace('examad_config',
                array('param_name' => array('text', $param->name)),
                array('param_value' => array('text', (string) $param->value))
            );
        }
    }
}