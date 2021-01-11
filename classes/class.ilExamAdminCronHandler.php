<?php

require_once (__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');

class ilExamAdminCronHandler
{
    /** @var  ilAccessHandler $access */
    protected $access;

    /** @var  ilLanguage $lng */
    protected $lng;

    /** @var ilDBInterface */
    protected $db;

    /** @var ilExamAdminPlugin $plugin */
    protected $plugin;

    /** @var ilExamAdminConfig */
    protected $config;

    /** @var ilExamAdminConnector */
    protected $connector;


    /**
     * constructor
     * @param ilExamAdminPlugin $plugin
     */
    public function __construct($plugin)
    {
        global $DIC;

        $this->access = $DIC->access();
        $this->lng = $DIC->language();
        $this->db = $DIC->database();

        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
        $this->connector = $plugin->getConnector();

        // prepare remote db access
        $this->plugin->init();
    }

    /**
     * Create or configure courses from the scheduled exams
     * @return array   $ref_id => title
     */
    public function installCourses()
    {
        $objects = $this->connector->getOrgaObjects();

        $collection = ilExamAdminOrgaRecord::getCollection()
            ->where(['obj_id' => array_keys($objects)])
            ->where(['exam_format'=>  explode(',', $this->config->get('exam_format'))]);

        $courses = [];

        /** @var ilExamAdminOrgaRecord $record */
        foreach ($collection->get() as $record) {

            $courses[$record->id] = $record->exam_title;

            if ($ref_id = $this->findCourse($record)) {
                $this->updateCourse($record, $ref_id);
            }
            else {
                $ref_id = $this->createCourse($record);
                $this->updateCourse($record, $ref_id);
            }
        }

        return $courses;
    }


    /**
     * Find an existing course by the data of an orga record
     * @param ilExamAdminOrgaRecord $record
     * @return int ref_id
     */
    protected function findCourse($record)
    {
        return false;
    }


    /**
     * Create a course from the data of an orga record
     * @param ilExamAdminOrgaRecord $record
     * @return int ref_id
     */
    protected function createCourse($record)
    {
        $cat_ref_id = $this->findCategory($record->fau_unit);

        if (!$cat_ref_id) {
            $cat_ref_id = $this->createCategory($record->fau_unit);
        }

        $master_course = $this->config->get('master_course');
        return $this->copyCourse($master_course, $cat_ref_id);
    }

    /**
     * Find a category by its title
     * @param string $title
     * @return int ref_id
     */
    protected function findCategory($title)
    {
        $query = "
            SELECT r.ref_id
            FROM object_data o 
            INNER JOIN object_reference r ON r.obj_id = o.obj_id
            WHERE o.title = " . $this->db->quote($title, 'text') . "
            AND o.type = 'cat'
            AND r.deleted IS NULL
        ";

        $result = $this->db->query($query);
        if ($row = $this->db->fetchAssoc($result)) {
            return $row['ref_id'];
        }
        return false;
    }

    /**
     * Create a category by its title
     * @param string $title
     * @return int ref_id
     */
    protected function createCategory($title)
    {
        $parent_ref_id = $this->config->get('parent_category');

        $category = new ilObjCategory();
        $category->setTitle($title);
        $category->create();
        $category->createReference();
        $category->putInTree($parent_ref_id);
        $category->setPermissions($parent_ref_id);
        return $category->getRefId();
    }



    /**
     * Copy a course
     * @param int $sourceRefId
     * @param int targetRefId
     * @return int newRefId
     */
    protected function copyCourse($sourceRefId, $targetRefId, $typesToLink = [])
    {
        global $DIC;

        $tree = $DIC->repositoryTree();

        // prepare the copy options for all sub objects
        $options = array();
        $nodedata = $tree->getNodeData($sourceRefId);
        $nodearray = $tree->getSubTree($nodedata);
        foreach ($nodearray as $node) {
            if (in_array($node['type'], $typesToLink)) {
                $options[$node['ref_id']] = array("type" => ilCopyWizardOptions::COPY_WIZARD_LINK);
            }
            else {
                $options[$node['ref_id']] = array("type" => ilCopyWizardOptions::COPY_WIZARD_COPY);
            }
        }

        $source_object = ilObjectFactory::getInstanceByRefId($sourceRefId);
        $ret = $source_object->cloneAllObject(
            $_COOKIE[session_name()],
            $_COOKIE['ilClientId'],
            $source_object->getType(),
            $targetRefId,
            $sourceRefId,
            $options,
            false
        );
        return $ret['ref_id'];
    }

    /**
     * Update a course with the data from the orga record
     * @param ilExamAdminOrgaRecord $record
     * @param int $ref_id
     */
    protected function updateCourse($record, $ref_id)
    {
        $course = new ilObjCourse($ref_id);
        $title = $record->exam_date . ' ' . $record->exam_title;
        $course->setTitle($title);
        $course->setDescription($record->fau_lecturer);
        $course->setOfflineStatus(false);
        $course->update();

        $this->updateCourseParticipants($record, $course);
    }

    /**
     * Update the course participants with the data from the orga record
     * @param ilExamAdminOrgaRecord $record
     * @param int ilObjCourse $course
     */
    protected function updateCourseParticipants($record, $course)
    {
        require_once (__DIR__ . '/class.ilExamAdminCourseUsers.php');
        $users = new ilExamAdminCourseUsers($this->plugin, $course);

        // add owner as admin (with test accounts)
        $users->addParticipants([$record->owner_id], false, ilExamAdminCourseUsers::CAT_LOCAL_ADMIN_LECTURER);

        // add admnis as tutors (with test accounts)
        $usr_ids = [];
        foreach($this->connector->getUserDataByLoginList($record->getAdminsLogins()) as $user) {
            $usr_ids[] = $user['usr_id'];
        }
        $users->addParticipants($usr_ids, false, ilExamAdminCourseUsers::CAT_LOCAL_TUTOR_CORRECTOR);

        $root = $users->getSingleUserDataByLogin('root');
        $users->removeParticipants([$root['usr_id']]);
    }


    /**
     * Synchronize the logins of users
     */
    public function syncLogins()
    {

    }


}