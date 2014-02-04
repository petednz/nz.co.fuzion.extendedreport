<?php

class CRM_Extendedreport_Form_Report_Case_CaseWithActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_case';
  protected $skipACL = FALSE;
  protected $_customGroupAggregates = true;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  protected $_temporary = ' TEMPORARY ';
  protected $_aggregatesAddPercentage = TRUE;
  public $_drilldownReport = array();
  /**
   * PreConstrain means the query gets run twice - the first time for generating temp tables
   * which go in the from the second time around
   * @var unknown
   */
  protected $_preConstrain = TRUE;
  /**
   * Name of table that links activities to cases. The 'real' table is replaced with the name of a filtered
   * temp table during processing
   *
   * @var unknown
   */
  protected $_caseActivityTable = 'civicrm_case_activity';
  protected $_potentialCriteria = array(
  );

  function __construct() {
    $this->_customGroupExtended['civicrm_case'] = array(
      'extends' => array('Case'),
      'filters' => TRUE,
      'title'  => ts('Case'),
    );
    $this->_customGroupExtended['civicrm_activity'] = array(
      'extends' => array('Activity'),
      'filters' => TRUE,
      'title'  => ts('Activity'),
    );

    $this->_columns = $this->getColumns('Case', array(
      'fields' => false,)
    )
    + $this->getColumns('Contact', array())
    +  $this->_columns = $this->getColumns('Activity', array(
      'fields' => false,)
    );
    $this->_columns['civicrm_case']['fields']['id']['required'] = true;
    $this->_columns['civicrm_contact']['fields']['id']['required'] = true;
 //  $this->_columns['civicrm_case']['fields']['id']['alter_display'] = 'alterCaseID';
    $this->_columns['civicrm_case']['fields']['id']['title'] = 'Case';
    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = true;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_aggregateRowFields  = array(
      'case_civireport:id' => 'Case',
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields  = array(
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    parent::__construct();
  }
  /**
   * Generate a temp table to reflect the pre-constrained report group
   * This could be a group of contacts on whom we are going to do a series of contribution
   * comparisons.
   *
   * We apply where criteria from the form to generate this
   *
   * We create a temp table of their ids in the first instance
   * and use this as the base
   */
  function generateTempTable(){
    $tempTable = 'civicrm_report_temp_activities' . date('d_H_I') . rand(1, 10000);
    $sql = "CREATE {$this->_temporary} TABLE $tempTable
    (`case_id` INT(10) UNSIGNED NULL DEFAULT '0',
    `activity_id` INT(10) UNSIGNED NULL DEFAULT '0',
    INDEX `case_id` (`case_id`),
    INDEX `activity_id` (`activity_id`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "
      INSERT INTO $tempTable
      SELECT ccase.id, activity_id
      FROM civicrm_case ccase
      LEFT JOIN
        (SELECT
          {$this->_aliases['civicrm_case']}.id as case_id, activity_id
          {$this->_from} {$this->_where}
        ) as case_activities ON case_activities.case_id = ccase.id";
    CRM_Core_DAO::executeQuery($sql);
    $this->_caseActivityTable = $tempTable;
  }

  function fromClauses( ) {
    return array(
      'contact_from_case',
      'activity_from_case',
    );
  }

  /**
   * constrainedWhere applies to Where clauses applied AFTER the
   * 'pre-constrained' report temp tables are created. Here we only keep the clauses relating to
   * civicrm_case
   */
  function constrainedWhere(){
    $this->_where = " WHERE 1";
    foreach ($this->whereClauses as $table => $clauses) {
      if(substr($table, 0, 12) == 'civicrm_case') {
        foreach ($clauses as $clause) {
          if(!empty($clause)) {
            $this->_where .= " AND " . $clause;
          }
        }
      }
    }
  }
}