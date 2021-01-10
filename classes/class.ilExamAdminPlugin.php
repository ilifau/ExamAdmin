<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
 
/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilExamAdminPlugin extends ilUserInterfaceHookPlugin
{
    /** @var ilExamAdminConfig */
    protected $config;

    /** @var ilExamAdminConnector */
    protected $connector;


	public function getPluginName()
	{
		return "ExamAdmin";
	}

    protected function afterUninstall()
    {
        global $DIC;

       $ilDB = $DIC->database();

        $ilDB->dropTable('examad_config');
        $ilDB->dropTable('examad_data');
    }

    /**
     * Get the data set for an object
     * @param $obj_id
     * @return ilExamAdminData
     */
	public function getData($obj_id)
    {
        $this->includeClass('class.ilExamAdminData.php');
        return new ilExamAdminData($this, $obj_id);
    }


    /**
     * Get the plugin configuration
     * @return ilExamAdminConfig
     */
    public function getConfig()
    {
        if (!isset($this->config))
        {
            require_once(__DIR__ . '/param/class.ilExamAdminConfig.php');
            $this->config = new ilExamAdminConfig($this);
        }
        return $this->config;
    }

    /**
     * Get the connector object
     * @return ilExamAdminConnector|null
     */
    public function getConnector()
    {
        if (!isset($this->connector))
        {
            require_once(__DIR__ . '/connector/class.ilExamAdminConnector.php');
            try
            {
                $this->connector = ilExamAdminConnector::getInstance($this);
            }
            catch (Exception $e)
            {
                return null;
            }

        }
        return $this->connector;
    }

    /**
     * Do initialisations
     */
    public function init()
    {
        require_once (__DIR__ . '/connector/class.ilExamAdminArConnectorDB.php');
        ilExamAdminArConnectorDB::register();
    }

    /**
     * Check if the object type is allowed
     */
    public function isAllowedType($type)
    {
        return in_array($type, array('crs', 'grp'));
    }


    /**
     * Get the ref_id of the course for a repository position
     * @param int $ref_id
     * @return int|false
     */
    public function getCourseRefId($ref_id)
    {
        global $DIC;

        if (ilObject::_lookupType($ref_id, true) == 'crs') {
            return $ref_id;
        }

        $path = $DIC->repositoryTree()->getNodePath($ref_id);
        foreach ($path as $node) {
            if ($node['type'] == 'crs') {
                return $node['child'];
            }
        }

        return false;
    }

    /**
     * Check if the user has administrative access
     * @return bool
     */
    public function hasAdminAccess()
    {
        global $DIC;
        return $DIC->rbac()->system()->checkAccess("visible", SYSTEM_FOLDER_ID);
    }


    /**
	 * Get a user preference
	 * @param string	$name
	 * @param mixed		$default
	 * @return mixed
	 */
	public function getUserPreference($name, $default = false)
	{
		global $ilUser;
		$value = $ilUser->getPref($this->getId().'_'.$name);
		if ($value !== false)
		{
			return $value;
		}
		else
		{
			return $default;
		}
	}


	/**
	 * Set a user preference
	 * @param string	$name
	 * @param mixed		$value
	 */
	public function setUserPreference($name, $value)
	{
		global $ilUser;
		$ilUser->writePref($this->getId().'_'.$name, $value);
	}


    /**
     * Handle a call by the cron job plugin
     * @return	int		Number of created archives
     * @throws	Exception
     */
    public function handleCronJob()
    {
        $created = 0;

        try
        {
            require_once (__DIR__ . '/class.ilExamAdminCronHandler.php');
            $handler = new ilExamAdminCronHandler($this);
            $courses = $handler->installCourses();

            $created = count($courses);
        }
        catch (exception $e)
        {
            $error = $e->getMessage();
        }

        if (!empty($error)) {
            throw new Exception($error);
        }

        return $created;
    }
}

?>