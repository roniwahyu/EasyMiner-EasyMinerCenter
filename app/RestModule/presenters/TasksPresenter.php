<?php

namespace EasyMinerCenter\RestModule\Presenters;

use Drahak\Restful\InvalidStateException;
use EasyMinerCenter\Exceptions\EntityNotFoundException;
use EasyMinerCenter\Model\Data\Facades\DatabasesFacade;
use EasyMinerCenter\Model\EasyMiner\Entities\Metasource;
use EasyMinerCenter\Model\EasyMiner\Entities\Task;
use EasyMinerCenter\Model\EasyMiner\Facades\MinersFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\TasksFacade;
use EasyMinerCenter\Model\EasyMiner\Serializers\GuhaPmmlSerializer;
use Nette\Application\BadRequestException;

/**
 * Class TasksPresenter - presenter pro práci s jednotlivými úlohami
 * @package EasyMinerCenter\RestModule\Presenters
 */
class TasksPresenter extends BaseResourcePresenter {
  /** @var  TasksFacade $tasksFacade */
  private $tasksFacade;
  /** @var  MinersFacade $minersFacade */
  private $minersFacade;
  /** @var  DatabasesFacade $databasesFacade */
  private $databasesFacade;

  #region actionReadPmml
  /**
   * Akce vracející PMML data konkrétní úlohy
   * @param int $id
   * @throws BadRequestException
   * @SWG\Get(
   *   tags={"Tasks"},
   *   path="/tasks/{id}/pmml",
   *   summary="Get PMML export of the task",
   *   security={{"apiKey":{}},{"apiKeyHeader":{}}},
   *   produces={"application/xml"},
   *   @SWG\Parameter(
   *     name="id",
   *     description="Task ID",
   *     required=true,
   *     type="integer",
   *     in="path"
   *   ),
   *   @SWG\Response(
   *     response=200,
   *     description="Task PMML",
   *     @SWG\Schema(type="xml")
   *   ),
   *   @SWG\Response(response=404, description="Requested task was not found."),
   *   @SWG\Response(response=500, description="Task has not been solved.")
   * )
   */
  public function actionReadPmml($id){
    $task=$this->findTaskWithCheckAccess($id);

    if ($task->state!=Task::STATE_SOLVED){
      throw new InvalidStateException("Task has not been solved!");
    }

    $pmml=$this->prepareTaskPmml($task);
    $this->sendXmlResponse($pmml);
  }

  /**
   * @param $task
   * @return \SimpleXMLElement
   */
  private function prepareTaskPmml(Task $task){
    //TODO refaktorovat - zároveň je totožná konstrukce použita v TaskPresenteru v modulu EasyMiner
    /** @var Metasource $metasource */
    $metasource=$task->miner->metasource;
    $this->databasesFacade->openDatabase($metasource->getDbConnection());
    $pmmlSerializer=new GuhaPmmlSerializer($task,null,$this->databasesFacade);
    $pmmlSerializer->appendTaskSettings();
    $pmmlSerializer->appendDataDictionary();
    $pmmlSerializer->appendTransformationDictionary();
    $pmmlSerializer->appendRules();
    return $pmmlSerializer->getPmml();
  }
  #endregion

  #region actionRead
  /**
   * Akce vracející detaily konkrétní úlohy
   * @param int $id
   * @throws BadRequestException
   * @SWG\Get(
   *   tags={"Tasks"},
   *   path="/tasks/{id}",
   *   summary="Get task details",
   *   security={{"apiKey":{}},{"apiKeyHeader":{}}},
   *   produces={"application/json","application/xml"},
   *   @SWG\Parameter(
   *     name="id",
   *     description="Task ID",
   *     required=true,
   *     type="integer",
   *     in="path"
   *   ),
   *   @SWG\Response(
   *     response=200,
   *     description="Task details",
   *     @SWG\Schema(ref="#/definitions/TaskResponse")
   *   ),
   *   @SWG\Response(response=404, description="Requested task was not found.")
   * )
   */
  public function actionRead($id){
    $task=$this->findTaskWithCheckAccess($id);
    $this->resource=$task->getDataArr(true);
    $this->sendResource();
  }
  #endregion

  /**
   * Akce pro zadání úlohy...
   */
  public function actionPost() {
    //FIXME implement
  }

  #region actionStart/actionStop
  /**
   * Akce pro spuštění dolování konkrétní úlohy
   * @param int $id
   * @SWG\Get(
   *   tags={"Tasks"},
   *   path="/tasks/{id}/start",
   *   summary="Start the solving of the data mining task",
   *   security={{"apiKey":{}},{"apiKeyHeader":{}}},
   *   produces={"application/json","application/xml"},
   *   @SWG\Parameter(
   *     name="id",
   *     description="Task ID",
   *     required=true,
   *     type="integer",
   *     in="path"
   *   ),
   *   @SWG\Response(
   *     response=200,
   *     description="Task state",
   *     @SWG\Schema(
   *       ref="#/definitions/TaskSimpleResponse"
   *     )
   *   ),
   *   @SWG\Response(response=404, description="Requested task was not found.")
   * )
   */
  public function actionReadStart($id) {
    $task=$this->findTaskWithCheckAccess($id);
    $miningDriver=$this->minersFacade->getTaskMiningDriver($task);
    if ($task->state==Task::STATE_NEW){
      //runTask
      $taskState=$miningDriver->startMining();
    }else{
      //check task state
      $taskState=$miningDriver->checkTaskState();
    }
    $this->tasksFacade->updateTaskState($task,$taskState);
    //send task simple details
    $this->resource=$task->getDataArr(false);
    $this->sendResource();
  }

  /**
   * Akce pro zastavení dolování konkrétní úlohy
   * @param int $id
   * @SWG\Get(
   *   tags={"Tasks"},
   *   path="/tasks/{id}/stop",
   *   summary="Stop the solving of the data mining task",
   *   security={{"apiKey":{}},{"apiKeyHeader":{}}},
   *   produces={"application/json","application/xml"},
   *   @SWG\Parameter(
   *     name="id",
   *     description="Task ID",
   *     required=true,
   *     type="integer",
   *     in="path"
   *   ),
   *   @SWG\Response(
   *     response=200,
   *     description="Task state",
   *     @SWG\Schema(
   *       ref="#/definitions/TaskSimpleResponse"
   *     )
   *   ),
   *   @SWG\Response(response=404, description="Requested task was not found.")
   * )
   */
  public function actionReadStop($id) {
    $task=$this->findTaskWithCheckAccess($id);
    $miningDriver=$this->minersFacade->getTaskMiningDriver($task);
    //stop the run
    $taskState=$miningDriver->stopMining();
    $this->tasksFacade->updateTaskState($task,$taskState);
    //send task simple details
    $this->resource=$task->getDataArr(false);
    $this->sendResource();
  }
  #endregion actionStart/actionStop


  #region actionReadRules
  /**
   * Akce vracející jeden konkrétní ruleset se základním přehledem pravidel
   * @param int $id
   * @SWG\Get(
   *   tags={"Tasks"},
   *   path="/tasks/{id}/rules",
   *   summary="List rules founded using the selected task",
   *   security={{"apiKey":{}},{"apiKeyHeader":{}}},
   *   produces={"application/json","application/xml"},
   *   @SWG\Parameter(
   *     name="id",
   *     description="Task ID",
   *     required=true,
   *     type="integer",
   *     in="path"
   *   ),
   *   @SWG\Response(
   *     response=200,
   *     description="List of rules",
   *     @SWG\Schema(
   *       @SWG\Property(property="task",ref="#/definitions/TaskSimpleResponse"),
   *       @SWG\Property(
   *         property="rules",
   *         type="array",
   *         @SWG\Items(ref="#/definitions/TaskRuleResponse")
   *       )
   *     )
   *   ),
   *   @SWG\Response(response=404, description="Requested task was not found."),
   *   @SWG\Response(response=500, description="Task has not been solved.")
   * )
   */
  public function actionReadRules($id){
    $task=$this->findTaskWithCheckAccess($id);
    if ($task->state!=Task::STATE_SOLVED){
      throw new InvalidStateException("Task has not been solved!");
    }
    $result=[
      'task'=>$task->getDataArr(),
      'rules'=>[]
    ];
    if ($task->rulesCount>0){
      $rules=$task->rules;
      if (!empty($rules)){
        foreach($rules as $rule){
          $result['rules'][]=$rule->getBasicDataArr();
        }
      }
    }
    $this->resource=$result;
    $this->sendResource();
  }
  #endregion actionReadRules



  /**
   * Funkce pro nalezení úlohy dle zadaného ID a kontrolu oprávnění aktuálního uživatele pracovat s daným pravidlem
   *
   * @param int $taskId
   * @return Task
   * @throws \Nette\Application\BadRequestException
   */
  private function findTaskWithCheckAccess($taskId){
    try{
      /** @var Task $task */
      $task=$this->tasksFacade->findTask($taskId);
    }catch (EntityNotFoundException $e){
      $this->error('Requested task was not found.');
      return null;
    }
    $this->minersFacade->checkMinerAccess($task->miner,$this->getCurrentUser());
    return $task;
  }


  #region injections
  /**
   * @param TasksFacade $tasksFacade
   */
  public function injectTasksFacade(TasksFacade $tasksFacade) {
    $this->tasksFacade=$tasksFacade;
  }
  /**
   * @param MinersFacade $minersFacade
   */
  public function injectMinersFacade(MinersFacade $minersFacade) {
    $this->minersFacade=$minersFacade;
  }
  /**
   * @param DatabasesFacade $databasesFacade
   */
  public function injectDatabasesFacade(DatabasesFacade $databasesFacade) {
    $this->databasesFacade=$databasesFacade;
  }
  #endregion injections
}


/**
 * @SWG\Definition(
 *   definition="TaskSimpleResponse",
 *   title="TaskSimpleDetails",
 *   required={"id","miner","type","name","state","rulesCount"},
 *   @SWG\Property(property="id",type="integer",description="Unique ID of the task"),
 *   @SWG\Property(property="miner",type="integer",description="ID of the associated miner"),
 *   @SWG\Property(property="type",type="integer",description="Type of the miner"),
 *   @SWG\Property(property="name",type="string",description="Human-readable name of the task"),
 *   @SWG\Property(property="state",type="string",description="State of the task",enum={"new","in_progress","solved","failed","interrupted","solved_heads"}),
 *   @SWG\Property(property="rulesCount",type="integer",description="Count of founded rules")
 * )
 * @SWG\Definition(
 *   definition="TaskResponse",
 *   title="Task",
 *   required={"id","miner","type","name","state","rulesCount"},
 *   @SWG\Property(property="id",type="integer",description="Unique ID of the task"),
 *   @SWG\Property(property="miner",type="integer",description="ID of the associated miner"),
 *   @SWG\Property(property="type",type="integer",description="Type of the miner"),
 *   @SWG\Property(property="name",type="string",description="Human-readable name of the task"),
 *   @SWG\Property(property="state",type="string",description="State of the task",enum={"new","in_progress","solved","failed","interrupted","solved_heads"}),
 *   @SWG\Property(property="rulesCount",type="integer",description="Count of founded rules"),
 *   @SWG\Property(
 *     property="taskSettings",
 *     description="Structured configuration of the task settings",
 *     @SWG\Property(property="limitHits",type="integer",description="Limit count of rules"),
 *     @SWG\Property(
 *       property="rule0",
 *       description="Rule pattern",
 *       @SWG\Property(property="antecedent",description="Antecedent pattern",ref="#/definitions/CedentDetailsResponse"),
 *       @SWG\Property(property="IMs",type="array",@SWG\Items(ref="#/definitions/TaskIMResponse")),
 *       @SWG\Property(property="succedent",description="Consequent pattern",ref="#/definitions/CedentDetailsResponse")
 *     ),
 *     @SWG\Property(property="strict",type="boolean",description="Strict require attributes in the pattern")
 *   )
 * )
 *
 * @SWG\Definition(
 *   definition="TaskIMResponse",
 *   title="IM",
 *   @SWG\Property(property="name",type="string"),
 *   @SWG\Property(property="localizedName",type="string"),
 *   @SWG\Property(property="thresholdType",type="string"),
 *   @SWG\Property(property="compareType",type="string"),
 *   @SWG\Property(
 *     property="fields",
 *     type="array",
 *     @SWG\Items(ref="#/definitions/TaskConfigFieldDetails")
 *   ),
 *   @SWG\Property(property="threshold",type="number"),
 *   @SWG\Property(property="alpha",type="number"),
 * )
 * @SWG\Definition(
 *   definition="TaskConfigFieldDetails",
 *   title="FieldDetails",
 *   required={"name","value"},
 *   @SWG\Property(property="name",type="string"),
 *   @SWG\Property(property="value",type="number")
 * )
 * @SWG\Definition(
 *   definition="CedentDetailsResponse",
 *   title="CedentDetails",
 *   @SWG\Property(property="type",type="string"),
 *   @SWG\Property(
 *     property="connective",
 *     @SWG\Property(property="id",type="integer"),
 *     @SWG\Property(property="name",type="string"),
 *     @SWG\Property(property="type",type="string"),
 *   ),
 *   @SWG\Property(property="level",type="integer"),
 *   @SWG\Property(property="children",type="array",
 *     @SWG\Items(ref="#/definitions/TaskSettingsAttributeDetails")
 *   ),
 * )
 * @SWG\Definition(
 *   definition="TaskSettingsAttributeDetails",
 *   title="AttributeDetails",
 *   @SWG\Property(property="name",type="string"),
 *   @SWG\Property(property="category",type="string"),
 *   @SWG\Property(property="ref",type="string"),
 *   @SWG\Property(
 *     property="fields",
 *     type="array",
 *     @SWG\Items(ref="#/definitions/TaskConfigFieldDetails")
 *   ),
 *   @SWG\Property(property="sign",type="string",enum={"positive"}),
 * )
 *
 * @SWG\Definition(
 *   definition="TaskRuleResponse",
 *   title="Rule",
 *   required={"id","text"},
 *   @SWG\Property(property="id",type="integer",description="Unique ID of the rule"),
 *   @SWG\Property(property="text",type="string",description="Human-readable form of the rule"),
 *   @SWG\Property(property="a",type="string",description="A value from the four field table"),
 *   @SWG\Property(property="b",type="string",description="B value from the four field table"),
 *   @SWG\Property(property="c",type="string",description="C value from the four field table"),
 *   @SWG\Property(property="d",type="string",description="D value from the four field table"),
 *   @SWG\Property(property="selected",type="string",enum={"0","1"},description="1, if the rule is in Rule Clipboard"),
 * )
 *
 *
 * @SWG\Definition(
 *   definition="TaskSimpleInput",
 *   title="TaskSimpleConfig",
 *   required={"miner","name","simpleSettings"},
 *   @SWG\Property(property="miner",type="integer",description="ID of the miner for this task"),
 *   @SWG\Property(property="name",type="string",description="Human-readable name of the task"),
 *   @SWG\Property(property="simpleSettings",type="string",description="Task configuration")
 * )
 * TODO konfigurace simpleSettings
 *
 */