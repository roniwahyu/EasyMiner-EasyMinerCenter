<?php

namespace EasyMinerCenter\EasyMinerModule\Presenters;
use EasyMinerCenter\Libs\RequestHelper;
use EasyMinerCenter\Model\Data\Facades\DatabasesFacade;
use EasyMinerCenter\Model\EasyMiner\Entities\Metasource;
use EasyMinerCenter\Model\EasyMiner\Entities\Task;
use EasyMinerCenter\Model\EasyMiner\Facades\RulesFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\TasksFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\UsersFacade;
use EasyMinerCenter\Model\EasyMiner\Serializers\AssociationRulesXmlSerializer;
use EasyMinerCenter\Model\EasyMiner\Serializers\GuhaPmmlSerializer;
use EasyMinerCenter\Model\EasyMiner\Serializers\XmlSerializersFactory;
use EasyMinerCenter\Model\EasyMiner\Transformators\XmlTransformator;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Utils\Json;

/**
 * Class TasksPresenter - presenter pro práci s úlohami...
 * @package EasyMinerCenter\EasyMinerModule\Presenters
 */
class TasksPresenter  extends BasePresenter{
  use MinersFacadeTrait;
  use ResponsesTrait;

  /** @var RulesFacade $rulesFacade */
  private $rulesFacade;
  /** @var TasksFacade $tasksFacade */
  private $tasksFacade;
  /** @var DatabasesFacade $databasesFacade */
  private $databasesFacade;
  /** @var  UsersFacade $usersFacade */
  private $usersFacade;
  /** @var XmlTransformator $xmlTransformator */
  private $xmlTransformator;
  /** @var  XmlSerializersFactory $xmlSerializersFactory */
  private $xmlSerializersFactory;

  /**
   * Akce pro spuštění dolování či zjištění stavu úlohy (vrací JSON)
   * @param string $miner
   * @param string $task
   * @param string $data
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionStartMining($miner,$task,$data){
    $taskUuid=$task;
    /****************************************************************************************************************/
    //nalezení daného mineru a kontrola oprávnění uživatele pro přístup k němu
    $miner=$this->findMinerWithCheckAccess($miner);
    //nalezení či připravení úlohy...
    $task=$this->tasksFacade->prepareTaskWithUuid($miner,$taskUuid);
    if ($task->state=='new'){
      #region nově importovaná úloha
      //zjištění názvu úlohy z jsonu s nastaveními
      $dataArr=Json::decode($data,Json::FORCE_ARRAY);
      //konfigurace úlohy
      if (!empty($dataArr['taskName'])){
        $task->name=$dataArr['taskName'];
      }else{
        $task->name=$taskUuid;
      }
      $task->taskSettingsJson=$data;
      $this->tasksFacade->saveTask($task);
      $miningDriver=$this->minersFacade->getTaskMiningDriver($task,$this->getCurrentUser());
      $taskState=$miningDriver->startMining();
      #endregion
    }else{
      #region zjištění stavu již existující úlohy
      $miningDriver=$this->minersFacade->getTaskMiningDriver($task,$this->getCurrentUser());
      $taskState=$miningDriver->checkTaskState();
      #endregion
    }

    $this->tasksFacade->updateTaskState($task,$taskState);
    if ($taskState->importState==Task::IMPORT_STATE_WAITING){
      //pokud máme na serveru čekající import, zkusíme poslat požadavek na jeho provedení
      RequestHelper::sendBackgroundGetRequest($this->getBackgroundImportLink($task));
    }
    $taskState=$task->getTaskState();
    $this->sendJsonResponse($taskState->asArray());
  }


  /**
   * Akce pro spuštění importu dat, která jsou již uložena v TEMP složce na tomto serveru
   * @param int $task
   */
  public function actionImportMiningResults($task){
    //načtení příslušné úlohy
    try {
      $task=$this->tasksFacade->findTask($task);
      $miningDriver=$this->minersFacade->getTaskMiningDriver($task,$task->miner->user);
    }catch (\Exception $e){
      //pokud nebyla nalezena příslušná úloha, není co importovat
      $this->terminate();
      return;
    }

    //import budeme provádět pouze v případě, že zatím neběží jiný import (abychom předešli konfliktům a zahlcení serveru)
    if ($task->importState==Task::IMPORT_STATE_WAITING){
      //zakážeme ukončení načítání
      ignore_user_abort(true);
      //označíme částečný probíhající import
      $taskState=$task->getTaskState();
      $importData=$taskState->getImportData();
      if (empty($importData)){
        $this->terminate();
      }
      $taskState->importState=Task::IMPORT_STATE_PARTIAL;
      $this->tasksFacade->updateTaskState($task,$taskState);
      //provedeme import plného PMML (klidně jen částečných výsledků) a zaktualizujeme stav úlohy
      $taskState=$miningDriver->importResultsPMML();
      $this->tasksFacade->updateTaskState($task,$taskState);

      //spustíme další dílší import
      RequestHelper::sendBackgroundGetRequest($this->getBackgroundImportLink($task));
    }
    $this->terminate();
  }

  /**
   * Akce pro zastavení dolování
   * @param string $miner
   * @param string $task
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionStopMining($miner,$task){
    //nalezení daného mineru a kontrola oprávnění uživatele pro přístup k němu
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);

    $miningDriver=$this->minersFacade->getTaskMiningDriver($task,$this->getCurrentUser());
    $taskState=$miningDriver->stopMining();
    $this->tasksFacade->updateTaskState($task,$taskState);

    $this->sendJsonResponse($taskState->asArray());
  }

  /**
   * Akce vracející pravidla pro vykreslení v easymineru
   * @param $miner
   * @param $task
   * @param $offset
   * @param $limit
   * @param $order
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionGetRules($miner,$task,$offset=0,$limit=25,$order='rule_id'){
    if ($order==''){
      $order='rule_id';
    }
    //nalezení daného mineru a kontrola oprávnění uživatele pro přístup k němu
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);

    $rules=$this->rulesFacade->findRulesByTask($task,$order,$offset,$limit);

    $rulesArr=array();
    if (!empty($rules)){
      foreach ($rules as $rule){
        $rulesArr[$rule->ruleId]=$rule->getBasicDataArr();
      }
    }
    $this->sendJsonResponse(array('task'=>array('name'=>$task->name,'rulesCount'=>$task->rulesCount,'IMs'=>$task->getInterestMeasures(),'state'=>$task->state,'importState'=>$task->importState),'rules'=>$rulesArr));
  }

  /**
   * Akce pro přejmenování úlohy v DB
   * @param int $miner
   * @param string $task
   * @param string $name
   * @throws \Exception
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionRenameTask($miner,$task,$name){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    $task->name=$name;
    if (!$this->tasksFacade->saveTask($task)){
      throw new \Exception($this->translator->translate('Task rename failed!'));
    }
    $this->sendJsonResponse(array('state'=>'ok'));
  }

  /**
   * Akce pro přejmenování úlohy v DB
   * @param int $miner
   * @param string $task
   * @param string $rulesOrder
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws \Exception
   */
  public function actionRulesOrder($miner,$task,$rulesOrder=''){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    if ($rulesOrder!=''){
      //je zadané nové pořadí pravidel...
      try{
        $task->setRulesOrder($rulesOrder);
      }catch (\Exception $e){
        throw new \Exception($this->translator->translate('Rules order was not saved!'));
      }
    }
    $this->sendJsonResponse(['task'=>$task->taskId,'rulesOrder'=>$rulesOrder]);
  }

  /**
   * Akce pro vygenerování detailů úlohy ve formátu PMML
   * @param $miner
   * @param $task
   * @throws \Exception
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function renderTaskPMML($miner,$task){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    //vygenerování a odeslání PMML
    $pmml=$this->prepareTaskPmml($task);
    $this->sendXmlResponse($pmml);
  }

  /**
   * Akce pro vygenerování zadání úlohy ve formátu PMML
   * @param $miner
   * @param $task
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function renderTaskSettingPMML($miner,$task) {
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    $pmml=$this->prepareTaskSettingPmml($task);
    $this->sendXmlResponse($pmml);
  }

  /**
   * Akce pro vykreslení detailů úlohy ve formátu PMML
   * @param $miner
   * @param $task
   * @throws \Exception
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function renderTaskXML($miner,$task){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    //vygenerování a odeslání PMML
    $associationRulesXmlSerializer=new AssociationRulesXmlSerializer($task->rules);
    $this->sendXmlResponse($associationRulesXmlSerializer->getXml());
  }

  /**
   * Akce pro vykreslení detailů úlohy ve formátu PMML
   * @param $miner
   * @param $task
   * @throws \Exception
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function renderTaskDRL($miner,$task){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $miner=$task->miner;
    $this->checkMinerAccess($miner);
    //vygenerování a odeslání PMML
    $associationRulesXmlSerializer=new AssociationRulesXmlSerializer($task->rules);
    $xml=$associationRulesXmlSerializer->getXml();
    $this->sendTextResponse($this->xmlTransformator->transformToDrl($xml/*,$this->template->basePath*/));
  }

  /**
   * Akce pro vykreslení detailů úlohy ve formátu PMML
   * @param string $miner
   * @param string $task
   * @throws \Exception
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function renderTaskDetails($miner,$task){
    $task=$this->tasksFacade->findTaskByUuid($miner,$task);
    $this->checkMinerAccess($task->miner);
    //vygenerování PMML
    $pmml=$this->prepareTaskPmml($task);
    $this->template->task=$task;
    $this->template->content=$this->xmlTransformator->transformToHtml($pmml);
  }

  /**
   * Funkce inicializující PmmlSerializer za využití konfigurace úlohy
   * @param Task $task
   * @param bool $includeFrequencies=true
   * @return GuhaPmmlSerializer
   */
  private function initPmmlSerializer(Task $task, $includeFrequencies=true) {
    /** @var Metasource $metasource */
    $metasource=$task->miner->metasource;
    $this->databasesFacade->openDatabase($metasource->getDbConnection());
    $pmmlSerializer=$this->xmlSerializersFactory->createGuhaPmmlSerializer($task,null,$this->databasesFacade);
    $pmmlSerializer->appendTaskSettings();
    $pmmlSerializer->appendDataDictionary($includeFrequencies);
    $pmmlSerializer->appendTransformationDictionary($includeFrequencies);
    return $pmmlSerializer;
  }

  /**
   * Funkce vracející PMML konfiguraci úlohy
   * @param Task $task
   * @return \SimpleXMLElement
   */
  private function prepareTaskSettingPmml(Task $task) {
    $pmmlSerializer=$this->initPmmlSerializer($task, false);
    return $pmmlSerializer->getPmml();
  }

  /**
   * Funkce vracející kompletní PMML export úlohy
   * @param Task $task
   * @return \SimpleXMLElement
   */
  private function prepareTaskPmml(Task $task){
    $pmmlSerializer=$this->initPmmlSerializer($task, true);
    $pmmlSerializer->appendRules();
    return $pmmlSerializer->getPmml();
  }


  /**
   * @return \EasyMinerCenter\Model\EasyMiner\Entities\User|null
   */
  private function getCurrentUser(){
    try{
      return $this->usersFacade->findUser($this->user->id);
    }catch (\Exception $e){
      /*ignore error (uživatel nemusí být přihlášen)*/
    }
    return null;
  }

  /**
   * Funkce vracející relativní URL pro import výsledků dolování (pro spuštění na pozadí)
   * @param Task $task
   * @return string
   */
  private function getBackgroundImportLink(Task $task){
    $this->absoluteUrls=false;
    return $this->link('importMiningResults',['task'=>$task->taskId]);
  }

  #region injections
  /**
   * @param RulesFacade $rulesFacade
   */
  public function injectRulesFacade(RulesFacade $rulesFacade) {
    $this->rulesFacade = $rulesFacade;
  }
  /**
   * @param TasksFacade $tasksFacade
   */
  public function injectTasksFacade(TasksFacade $tasksFacade){
    $this->tasksFacade=$tasksFacade;
  }
  /**
   * @param DatabasesFacade $databasesFacade
   */
  public function injectDatabasesFacade(DatabasesFacade $databasesFacade){
    $this->databasesFacade=$databasesFacade;
  }
  /**
   * @param XmlTransformator $xmlTransformator
   */
  public function injectXmlTransformator(XmlTransformator $xmlTransformator){
    $this->xmlTransformator=$xmlTransformator;
    //nastaven basePath
    $this->xmlTransformator->setBasePath($this->template->basePath);
  }

  /**
   * @param UsersFacade $usersFacade
   */
  public function injectUsersFacade(UsersFacade $usersFacade) {
    $this->usersFacade=$usersFacade;
  }

  /**
   * @param XmlSerializersFactory $xmlSerializersFactory
   */
  public function injectXmlSerializersFactory(XmlSerializersFactory $xmlSerializersFactory) {
    $this->xmlSerializersFactory=$xmlSerializersFactory;
  }
  #endregion
} 