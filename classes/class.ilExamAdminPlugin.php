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

    /** @var ilExamAdminConnector2 */
    protected $connector2;

    /** @var self */
    protected static $instance;


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
     * Get the plugin instance
     * @return ilExamAdminPlugin
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Get the data set for an object
     * @param $obj_id
     * @return ilExamAdminData
     */
	public function getData($obj_id)
    {
        require_once(__DIR__ . '/param/class.ilExamAdminData.php');
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
    * Get the connector object
    * @return ilExamAdminConnector2|null
    */
   public function getConnector2()
   {
       if (!isset($this->connector2))
       {
           try
           {
               $this->connector2 = ilExamAdminConnector2::getInstance($this);
           }
           catch (Exception $e)
           {
               return null;
           }

       }
       return $this->connector2;
   }    

    /**
     * Do initialisations
     */
    public function init()
    {
        require_once (__DIR__ . '/connector/class.ilExamAdminArConnectorDB.php');
        ilExamAdminArConnectorDB::register();
        ilExamAdminArConnectorDB2::register();
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
        if (!ilContext::usesHTTP()) {
            echo "ExamAdmin: handle cron job...\n";
        }

        require_once (__DIR__ . '/class.ilExamAdminCronHandler.php');
        $handler = new ilExamAdminCronHandler($this);
        
        // not needed if local_auth_remote is 1
        $handler->syncUserData();
        
        $courses = $handler->installCourses();
        $handled = count($courses);

        if (!ilContext::usesHTTP()) {
            echo "ExamAdmin: finished.\n";
        }

        return $handled;
    }
}