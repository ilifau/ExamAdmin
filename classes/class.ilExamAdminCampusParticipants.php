<?php


class ilExamAdminCampusParticipants extends ilSaxParser
{
    /** @var string */
    protected $cdata;

    /** @var string[] */
    protected $active_matriculations = [];

    /** @var string[] */
    protected $resigned_matriculations = [];

    /** @var string */
    protected $matriculation;

    /** @var bool */
    protected $resign;


    /**
     * Get the participants of an exam
     * @param ilExamAdminPlugin $plugin
     * @param $exam_id
     * @return array
     */
    public function fetchParticipants($plugin, $exam_id)
    {
        $this->active_matriculations = [];
        $this->resigned_matriculations = [];

        $xml= sprintf('
            <SOAPDataService active="y">
                <general>
                    <object>getParticipants</object>
                </general>
                <condition>
                    <porg.porgnr>%s</porg.porgnr>
                </condition>
            </SOAPDataService> 
        ', $exam_id);

        $client = new SoapClient($plugin->getConfig()->get('campus_soap_url') . '?wsdl');
        $result = $client->__call('getDataXML', ['xmlParams' => $xml]);

        $this->setXMLContent($result);
        $this->startParsing();
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
     * set event handlers
     *
     * @param	resource	reference to the xml parser
     * @access	private
     */
    public function setHandlers($a_xml_parser)
    {
        xml_set_object($a_xml_parser, $this);
        xml_set_element_handler($a_xml_parser, 'handlerBeginTag', 'handlerEndTag');
        xml_set_character_data_handler($a_xml_parser, 'handlerCharacterData');
    }

    /**
     * handler for begin of element
     *
     * @param	resource	$a_xml_parser		xml parser
     * @param	string		$a_name				element name
     * @param	array		$a_attribs			element attributes array
     */
    public function handlerBeginTag($a_xml_parser, $a_name, $a_attribs)
    {
        // Reset cdata
        $this->cdata = '';
    }

    /**
     * handler for end of element
     *
     * @param	resource	$a_xml_parser		xml parser
     * @param	string		$a_name				element name
     * @throws	ilSaxParserException	if invalid xml structure is given
     * @throws	ilWebLinkXMLParserException	missing elements
     */
    public function handlerEndTag($a_xml_parser, $a_name)
    {
        switch ($a_name) {

            case 'lab.mtknr':
                $this->matriculation = $this->cdata;
                break;

            case 'prueck':
                $this->resign = (bool) $this->cdata;
                break;

            case 'Participant':
                if ($this->resign) {
                    $this->resigned_matriculations[] = $this->matriculation;
                }
                else {
                    $this->active_matriculations[] = $this->matriculation;
                }
                break;
        }

        // Reset cdata
        $this->cdata = '';
    }


    /**
     * handler for character data
     *
     * @param	resource	$a_xml_parser		xml parser
     * @param	string		$a_data				character data
     */
    public function handlerCharacterData($a_xml_parser, $a_data)
    {
        $this->cdata .= trim($a_data);
    }
}