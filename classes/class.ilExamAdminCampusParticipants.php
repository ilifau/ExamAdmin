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
}