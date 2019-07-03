<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExamAdmin plugin data class
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilExamAdminData
{
    /** @var int obj_id */
    protected $obj_id;
	/**
	 * @var ilExamAdminParam[]	$params		parameters: 	name => ilExamAdminParam
	 */
	protected $params = array();

    /**
     * @var ilExamAdminPlugin
     */
	protected $plugin;


	/**
	 * Constructor.
	 * @param ilPlugin
     * @param int obj_id;
	 */
	public function __construct($a_plugin_object, $a_obj_id)
	{
		$this->plugin = $a_plugin_object;
		$this->obj_id = $a_obj_id;

        $this->plugin->includeClass('param/class.ilExamAdminParam.php');

        /** @var ilExamAdminParam[] $params */
        $params = [];

        // used to remember the selected import type (matriculations or ref_id)
        $params[] = ilExamAdminParam::_create(
            'source', '', '', ilExamAdminParam::TYPE_TEXT, 'matriculations'
        );

        // used to remember the ref_id for import
        $params[] = ilExamAdminParam::_create(
            'source_ref_id', '', '', ilExamAdminParam::TYPE_REF_ID
        );

        foreach ($params as $param)
        {
            $this->params[$param->name] = $param;
        }

        $this->read();
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

        $query = "SELECT * FROM examad_data WHERE obj_id = ". $ilDB->quote($this->obj_id, 'integer');
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
            $ilDB->replace('examad_data',
                array(
                    'obj_id' =>  array('integer', $this->obj_id),
                    'param_name' => array('text', $param->name)
                ),
                array('param_value' => array('text', (string) $param->value))
            );
        }
    }
}