<?php

class ilExamAdminOrgaCampusExam extends ActiveRecord
{
    /**
     * @return string
     * @description Return the Name of your Database Table
     */
    public static function returnDbTableName()
    {
        return 'xamo_campus';
    }

    /**
     * @var integer
     * @con_has_field        true
     * @con_is_primary       true
     * @con_sequence         false
     * @con_is_notnull       true
     * @con_fieldtype        integer
     * @con_length           4
     */
    public $porgnr;

    /**
     * @var integer
     * @con_has_field        true
     * @con_is_notnull       true
     * @con_fieldtype        integer
     * @con_length           4
     */
    public $pnr;

    /**
     * @var string
     * @con_has_field        true
     * @con_is_notnull       true
     * @con_fieldtype        text
     * @con_length           10
     */
    public $psem;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           10
     */
    public $ptermin;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           10
     */
    public $pdatum;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           10
     */
    public $ppruefer;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           50
     */
    public $vorname;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           50
     */
    public $nachname;


    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           500
     */
    public $titel;

    /**
     * @var string
     * @con_has_field        true
     * @con_fieldtype        text
     * @con_length           500
     */
    public $veranstaltung;


    /**
     * Get the label of the exam
     * @return string
     */
    public function getLabel()
    {
        $semester = $this->psem;
        $year = (int)  substr($semester, 0, 4);
        $num = (int) substr($semester, 4, 1);

        switch ($num) {
            case 1:
                $semester = 'SoSe ' . $year;
                break;
            case 2:
                $semester = 'WiSe ' . $year;
                break;
        }

        return $this->porgnr . ' - '
            . (empty($this->nachname) ? '' : $this->nachname . ', ' . $this->vorname .  ': ')
            . $this->titel . ' ( ' . $this->pnr . ', '. $semester . ', Termin ' . $this->ptermin. ')'
            . (empty($this->veranstaltung) ? '' : ': ' . $this->veranstaltung);
    }

    /**
     * Extract the key (porgnr) from a generated label
     */
    public static function getKeyFromLabel($label)
    {
        $dashpos = strpos($label, ' - ');
        return (int) substr($label, 0, $dashpos);
    }

    /**
     * get a list of semesters that are near a given semester
     * @param $semester
     * @return array
     */
    public static function getNearSemesters($semester)
    {
        $year = (int)  substr($semester, 0, 4);
        $num = (int) substr($semester, 4, 1);

        switch ($num) {
            case 1:
                return [
                    ($year - 1) . '2',
                    $semester,
                    $year . '2'
                ];
            case 2:
                return [
                    $year . '1',
                    $semester,
                    ($year + 1) . '1',
                ];
            default:
                return [$semester];
        }
    }
}
