<?php


class ilExamAdminCampusParticipants
{
    /** @var string[] */
    protected $active_matriculations = [];

    /** @var string[] */
    protected $resigned_matriculations = [];


    /**
     * Get the participants of an exam
     * @param ilExamAdminPlugin $plugin
     * @param $exam_id
     * @return array
     */
    public function fetchParticipants($plugin, $exam_id)
    {
        global $DIC;
        $db = $DIC->fau()->staging()->database();

        $this->active_matriculations = [];
        $this->resigned_matriculations = [];

        $query = "
            SELECT p.porgnr, p.prueck, i.schac_personal_unique_code
            FROM campo_exam_participants p
            INNER JOIN identities i ON p.person_id = i.fau_campo_person_id
            WHERE porgnr = " . $db->quote($exam_id, 'integer');
        $result = $db->query($query);

        while ($row = $db->fetchAssoc($result)) {
            $pos = strpos($row['schac_personal_unique_code'], 'Matrikelnummer:');
            if ($pos > 0) {
                $matriculation = substr($row['schac_personal_unique_code'], $pos + strlen('Matrikelnummer:'));
                if ($row['prueck'] > 0) {
                    $this->resigned_matriculations[] = $matriculation;
                }
                else {
                    $this->active_matriculations[] = $matriculation;
                }
            }
        }
    }

    /**
     * Get the matriculation numbers of active subscriptions
     */
    public function getActiveMatriculations()
    {
        return $this->active_matriculations;
    }

    /**
     * Get the matriculation numbers of resigned subscriptions
     */
    public function getResignedMatriculations()
    {
        return $this->resigned_matriculations;
    }

    /**
     * Update the course members from campo
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     */
    public function updateCourseMembers($record, $course, $plugin)
    {
        require_once (__DIR__ . '/class.ilExamAdminCourseUsers.php');

        $connObj = $plugin->getConnector();
        $usersObj = new ilExamAdminCourseUsers($plugin, $course);

        // get the matriculation numbers from campus
        $active_matriculations = [];
        $resigned_matriculations = [];
        foreach ($record->getExamIds() as $id) {
            if (!empty($id)) {
                $this->fetchParticipants($plugin, $id);
                $active_matriculations = array_merge($active_matriculations, $this->getActiveMatriculations());
                $resigned_matriculations = array_merge($resigned_matriculations, $this->getResignedMatriculations());
            }
        }

        // resign matching local users (only those that are not active for another exam_id)
        $resigned_matriculations = array_diff($resigned_matriculations, $active_matriculations);
        foreach( $usersObj->getUserDataByMatriculationList($resigned_matriculations) as $resigned_data) {
           $usersObj->removeParticipant($resigned_data['usr_id'], true);
        }

        // add matching remote users (create local users, if necessary)
        foreach ($connObj->getUserDataByMatriculationList($active_matriculations) as $active_data) {
            $local_data = $usersObj->getMatchingUser($active_data, true, $plugin->getConfig()->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE));
            $usersObj->addParticipant($local_data['usr_id'], ilExamAdminCourseUsers::ROLE_MEMBER);
        }
    }
}