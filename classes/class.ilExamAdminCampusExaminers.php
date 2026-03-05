<?php


class ilExamAdminCampusExaminers
{
    /**
     * Get the examiners of an exam
     * @param ilExamAdminPlugin $plugin
     * @param $exam_id
     * @return array
     */
    public function fetchExaminers($plugin, $exam_id): array
    {
        global $DIC;
        $db = $DIC->fau()->staging()->database();

        $examiners_campo_pk_persistent_id = [];
        $query = "
            SELECT p.porgnr, p.person_id, i.pk_persistent_id
            FROM campo_exam_examiner p
            INNER JOIN identities i ON p.person_id = i.fau_campo_person_id
            WHERE porgnr = " . $db->quote($exam_id, 'integer');
        $result = $db->query($query);

        while ($row = $db->fetchAssoc($result)) {
            $examiners_campo_pk_persistent_id[] = $row['pk_persistent_id'];
        }
        return $examiners_campo_pk_persistent_id;
    }
   
    
    /**
     * Update the course examiners from campo
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     */
    public function updateCourseExaminers($record, $course, $plugin)
    {
        $connObj = $plugin->getConnector2();
        $usersObj = new ilExamAdminCourseUsers($plugin, $course);

        // get the examiners from campo for all exams assigned to course
        $examiners_campo_pk_persistent_id = [];
        foreach ($record->getExamIds() as $id) {
            if (!empty($id)) {
                $examiners_campo_pk_persistent_id = array_merge($examiners_campo_pk_persistent_id, $this->fetchExaminers($plugin, $id));
            }
        }

        // add matching remote users (create local users, if necessary)
        foreach ($connObj->getUserDataByExternalAccountList($examiners_campo_pk_persistent_id) as $active_data) {
            $local_data = $usersObj->getMatchingUser($active_data, true, $plugin->getConfig()->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE));
            $usersObj->addParticipant($local_data['usr_id'], ilExamAdminCourseUsers::ROLE_ADMIN);
        }
    }
}