<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExamAdminOrgaCampusExamsInputGUI
 * extended to support form confirmation
 *
 * @ilCtrl_IsCalledBy ilExamAdminOrgaCampusExamsInputGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilExamAdminOrgaCampusExamsInputGUI: ilRepositorySearchGUI
 *
 */
class ilExamAdminOrgaCampusExamsInputGUI extends ilDclTextInputGUI
{
    /**
     * @var string
     */
    protected $semester;


    /**
     * ilExamOrgaExamsInputGUI constructor.
     * @param string $a_title
     * @param string $a_postvar
     */
    public function __construct($a_title = "", $a_postvar = "")
    {
        parent::__construct($a_title, $a_postvar);
        $this->setMulti(true);
    }

    /**
     * Set the semester for autocomplete restriction
     * @param string $semester
     */
    public function setAutocomplete($semester = null)
    {
        if (!empty($semester)) {
            $this->semester = $semester;
            $this->ctrl->setParameterByClass('ilexamadminorgacampusexamsinputgui', 'semester', $semester);
        }

        $ajax_url = $this->ctrl->getLinkTargetByClass(
            ['iluipluginroutergui', 'ilexamadminorgacampusexamsinputgui'],
            'doAutoComplete',
            '',
            true,
            false
        );
        $this->setDataSource($ajax_url);
    }


    /**
     * Execute command
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case 'doAutoComplete':
                $this->$cmd();
                break;
        }
    }

    /**
     * Auto-Complete the text inout
     * @see \ilRepositorySearchGUI::doUserAutoComplete
     */
    public function doAutoComplete()
    {
        require_once (__DIR__ . '/../class.ilExamAdminPlugin.php');
        ilExamAdminPlugin::getInstance()->init();

        $term = $_REQUEST['term'];
        $semester = $_REQUEST['semester'];
        $fetchall = $_REQUEST['fetchall'];

        require_once (__DIR__ . '/class.ilExamAdminOrgaCampusExam.php');
        $exams = ilExamAdminOrgaCampusExam::getCollection()
            ->where(['nachname' => $term . '%'] ,'LIKE')
            ->limit(0, $fetchall ? 1000 : 10);

        if (!empty($semester)) {
            $exams->where(['psem' => $semester]);
        }

        $items = [];

        /** @var  ilExamAdminOrgaCampusExam $exam */
        foreach($exams->get() as $exam) {
            $items[] = [
                'value'=> $exam->porgnr,
                'label' => $exam->getLabel(),
                'id' => 'porgnr_' . $exam->porgnr
            ];
        }

        $result_json['items'] = $items;
        $result_json['hasMoreResults'] = !$fetchall;

        echo json_encode($result_json);
        exit;
    }

     /**
     *
     * @return bool|void
     */
    public function checkInput()
    {
        // fault tolerance (field is multi, see constructor)
        if (!is_array($_POST[$this->getPostVar()])) {
            $_POST[$this->getPostVar()] = [];
        }

        require_once (__DIR__ . '/class.ilExamAdminOrgaCampusExam.php');
        foreach ($_POST[$this->getPostVar()] as $value) {

            if (empty(trim($value))) {
                continue;
            }

            $exams = ilExamAdminOrgaCampusExam::where(['porgnr' => (int) $value]);
            if (!empty($this->semester)) {
                $exams->where(['psem' => $this->semester]);
            }

            if (!$exams->hasSets()) {
                $this->setAlert(sprintf(ilExamAdminPlugin::getInstance()->txt('exam_not_found'), $value));
                return false;
            }
        }

        return parent::checkInput();
    }

    /**
     * Set the value by string
     * @param string $string
     */
    public function setValueByString($string)
    {
        $ids = [];
        foreach (explode(',', $string) as $entry) {
            $ids[] = trim($entry);
        }
        $this->setValue($ids);
    }
}