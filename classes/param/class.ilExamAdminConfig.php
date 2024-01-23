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
    const GLOBAL_ADMIN_ROLE = 'global_admin_role';
    const GLOBAL_LECTURER_ROLE = 'global_lecturer_role';
    const GLOBAL_PARTICIPANT_ROLE = 'global_participant_role';
    const LOCAL_TESTACCOUNT_ROLE = 'local_testaccount_role';
    const BASE_URL = 'base_url';

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
		//$this->plugin = $a_plugin_object;
		//$this->plugin->includeClass('param/class.ilExamAdminParam.php');

		/** @var ilExamAdminParam[] $params */
		$params = array();

        $params[] = ilExamAdminParam::_create(
            self::GLOBAL_ADMIN_ROLE,
            $a_plugin_object->txt('cat_global_admin_role'),
            $a_plugin_object->txt('cat_global_admin_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        $params[] = ilExamAdminParam::_create(
            self::GLOBAL_LECTURER_ROLE,
            $a_plugin_object->txt('cat_global_lecturer_role'),
            $a_plugin_object->txt('cat_global_lecturer_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        $params[] = ilExamAdminParam::_create(
            self::GLOBAL_PARTICIPANT_ROLE,
            $a_plugin_object->txt('cat_global_participant_role'),
            $a_plugin_object->txt('cat_global_participant_info'),
            ilExamAdminParam::TYPE_ROLE
        );

        $params[] = ilExamAdminParam::_create(
            self::LOCAL_TESTACCOUNT_ROLE,
            $a_plugin_object->txt('local_testaccount_role'),
            $a_plugin_object->txt('local_testaccount_role_info'),
            ilExamAdminParam::TYPE_TEXT,
            'Testaccount'
        );


        // format
        $params[] = ilExamAdminParam::_create(
            'exam_format',
            $a_plugin_object->txt('exam_format'),
            $a_plugin_object->txt('exam_format_info'),
            ilExamAdminParam::TYPE_MULTISELECT,
            'presence',
            [
                'presence' =>  $a_plugin_object->txt('exam_format_presence'),
                'presence_self' =>  $a_plugin_object->txt('exam_format_presence_self'),
                'open' =>  $a_plugin_object->txt('exam_format_open'),
                'monitored' => $a_plugin_object->txt('exam_format_monitored'),
                'admission' => $a_plugin_object->txt('exam_format_admission'),
            ]
        );

        // semester
        for ($y = 2020; $y <= 2030; $y++) {
            $options[$y . 's'] = $a_plugin_object->txt('summer_term') . ' ' . $y;
            $options[$y . 'w'] = $a_plugin_object->txt('winter_term') . ' ' . $y . '/' . ($y + 1);
        }
        $params[] = ilExamAdminParam::_create(
            'semester',
            $a_plugin_object->txt('semester'),
            $a_plugin_object->txt('semester_info'),
            ilExamAdminParam::TYPE_SELECT, '2020w',
            $options
        );

        $params[] = ilExamAdminParam::_create(
            'max_date',
            $a_plugin_object->txt('max_date'),
            $a_plugin_object->txt('max_date_info'),
            ilExamAdminParam::TYPE_TEXT,
        );


        // test data
        $params[] = ilExamAdminParam::_create(
            'testdata',
            $a_plugin_object->txt('testdata'),
            $a_plugin_object->txt('testdata_info'),
            ilExamAdminParam::TYPE_BOOLEAN,
            0
        );

        // master courses
        $params[] = ilExamAdminParam::_create(
            'master_course',
            $a_plugin_object->txt('master_course'),
            $a_plugin_object->txt('master_course_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );
        $params[] = ilExamAdminParam::_create(
            'master_course_presence',
            $a_plugin_object->txt('master_course_presence'),
            $a_plugin_object->txt('master_course_presence_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );
        $params[] = ilExamAdminParam::_create(
            'master_course_open',
            $a_plugin_object->txt('master_course_open'),
            $a_plugin_object->txt('master_course_open_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );
        $params[] = ilExamAdminParam::_create(
            'master_course_monitored',
            $a_plugin_object->txt('master_course_monitored'),
            $a_plugin_object->txt('master_course_monitored_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );
        $params[] = ilExamAdminParam::_create(
            'master_course_admission',
            $a_plugin_object->txt('master_course_admission'),
            $a_plugin_object->txt('master_course_admission_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );        

        // parent category
        $params[] = ilExamAdminParam::_create(
            'parent_category',
            $a_plugin_object->txt('parent_category'),
            $a_plugin_object->txt('parent_category_info'),
            ilExamAdminParam::TYPE_REF_ID,
            0
        );

        // base url
        $params[] = ilExamAdminParam::_create(
            self::BASE_URL,
            $a_plugin_object->txt('base_url'),
            $a_plugin_object->txt('base_url_info'),
            ilExamAdminParam::TYPE_TEXT,
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

    /**
     * Get the semester as it is stored in mein campus
     * @return string (format: '20201' (summer term) or '20202' (winter term)
     */
    public function getCampusSemester()
    {
        $semester = $this->get('semester');
        $semester = str_replace('s', '1', $semester);
        $semester = str_replace('w', '2', $semester);
        return $semester;
    }

}