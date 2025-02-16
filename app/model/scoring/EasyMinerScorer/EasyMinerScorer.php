<?php
namespace EasyMinerCenter\Model\Scoring\EasyMinerScorer;

use EasyMinerCenter\Model\Data\Facades\DatabasesFacade;
use EasyMinerCenter\Model\EasyMiner\Entities\Datasource;
use EasyMinerCenter\Model\EasyMiner\Entities\RuleSet;
use EasyMinerCenter\Model\EasyMiner\Entities\Task;
use EasyMinerCenter\Model\EasyMiner\Serializers\XmlSerializersFactory;
use EasyMinerCenter\Model\Scoring\IScorerDriver;
use EasyMinerCenter\Model\Scoring\ScoringResult;
use Nette\NotImplementedException;
use Nette\Utils\Json;

/**
 * Class EasyMinerScorer - driver pro práci se scorerem vytvořeným Jardou Kuchařem
 * @package EasyMinerCenter\Model\Scoring\ModelTester
 * @author Stanislav Vojíř
 */
class EasyMinerScorer implements IScorerDriver{
  /** @var  string $serverUrl */
  private $serverUrl;
  /** @var DatabasesFacade $databasesFacade */
  private $databasesFacade;
  /** @var  XmlSerializersFactory $xmlSerializersFactory */
  private $xmlSerializersFactory;
  /** @var array|null $params - pole připravené pro pracovní parametry tohoto driveru */
  public $params=[];

  const ROWS_PER_TEST=1000;

  /**
   * @param string $serverUrl - adresa koncového uzlu API, které je možné použít
   * @param DatabasesFacade $databasesFacade
   * @param XmlSerializersFactory $xmlSerializersFactory
   * @param array|null $params = null
   */
  public function __construct($serverUrl, DatabasesFacade $databasesFacade, XmlSerializersFactory $xmlSerializersFactory, $params=null){
    $this->serverUrl=trim($serverUrl,'/');
    $this->databasesFacade=$databasesFacade;
    $this->xmlSerializersFactory=$xmlSerializersFactory;
    $this->params=$params;
  }

  /**
   * @param Task $task
   * @param Datasource $testingDatasource
   * @return ScoringResult
   * @throws \Exception
   */
  public function evaluateTask(Task $task, Datasource $testingDatasource) {
    #region sestavení PMML a následné vytvoření scoreru
    $pmml=$this->prepareTaskPmml($task);
    $url=$this->serverUrl.'/scorer';

    try{
      $response=self::curlRequestResponse($url,$pmml,'',['Content-Type'=>'application/xml; charset=utf-8']);
      $response=Json::decode($response,Json::FORCE_ARRAY);
      if (@$response['code']==201 && !empty($response['id'])){
        $scorerId=$response['id'];
      }else{
        throw new \Exception(@$response['description']);
      }
    }catch (\Exception $e){
      throw new \Exception('Scorer creation failed!',500,$e);
    }
    #endregion sestavení PMML a následné vytvoření scoreru

    #region postupné posílání řádků z testovací DB tabulky
    $dbTable=$testingDatasource->dbTable;
    $this->databasesFacade->openDatabase($testingDatasource->getDbConnection());
    $dbRowsCount=$this->databasesFacade->getRowsCount($dbTable);
    $testedRowsCount=0;
    /** @var ScoringResult[] $partialResults */
    $partialResults=[];
    $url.='/'.$scorerId;
    //export jednotlivých řádků z DB a jejich otestování
    while($testedRowsCount<$dbRowsCount){
      //připravení JSONu a jeho odeslání
      $json=$this->databasesFacade->prepareJsonFromDatabaseRows($dbTable,$testedRowsCount,self::ROWS_PER_TEST);
      $response=self::curlRequestResponse($url,$json,'',['Content-Type'=>'application/json; charset=utf-8']);

      $response=Json::decode($response,Json::FORCE_ARRAY);
      if ($response["code"]!=200){
        throw new \Exception('Invalid scorer response!');
      }

      //vytvoření objektu s výsledky
      $scoringResult=new EasyMinerScoringResult($response);
      $partialResult=$scoringResult->getScoringConfusionMatrix()->getScoringResult(true);
      $partialResults[]=$partialResult;
      //TODO tady bude v budoucnu možné doplnit zpracování celé kontingenční tabulky

      //připočtení řádků a uvolnění paměti
      unset($scoringResult);
      $testedRowsCount+=self::ROWS_PER_TEST;
    }
    #endregion postupné posílání řádků z testovací DB tabulky
    #region sestavení celkového výsledku a jeho vrácení
    return ScoringResult::merge($partialResults);
    #endregion sestavení celkového výsledku a jeho vrácení
  }


  /**
   * Funkce pro vytvoření PMML z konkrétní úlohy
   * @param Task $task
   * @return string
   */
  private function prepareTaskPmml(Task $task){
    $this->databasesFacade->openDatabase($task->miner->metasource->getDbConnection());
    $pmmlSerializer=$this->xmlSerializersFactory->createGuhaPmmlSerializer($task,null,$this->databasesFacade);
    $pmmlSerializer->appendTaskSettings();
    $pmmlSerializer->appendDataDictionary(false);
    $pmmlSerializer->appendTransformationDictionary(false);
    $pmmlSerializer->appendRules();
    return $pmmlSerializer->getPmml()->asXML();
  }


  /**
   * @param RuleSet $ruleSet
   * @param Datasource $testingDatasource
   * @return ScoringResult
   */
  public function evaluateRuleSet(RuleSet $ruleSet, Datasource $testingDatasource) {
    // TODO: Implement evaluareRuleSet() method.
    throw new NotImplementedException();
  }

  /**
   * @param string $url
   * @param string $postData = ''
   * @param string $apiKey = ''
   * @param array $headersArr=[]
   * @return string - response data
   * @throws \Exception - curl error
   */
  private static function curlRequestResponse($url, $postData='', $apiKey='', $headersArr=[]){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch,CURLOPT_MAXREDIRS,0);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
    if (!empty($apiKey)){
      $headersArr['Authorization']='ApiKey '.$apiKey;
    }
    if ($postData!=''){
      curl_setopt($ch,CURLOPT_POST,true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
      $headersArr['Content-length']=strlen($postData);
    }
    $headersSendArr=[];
    if (!empty($headersArr)){
      foreach ($headersArr as $header=>$value){
        $headersSendArr[]=$header.': '.$value;
      }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headersSendArr);

    $responseData = curl_exec($ch);
    if(curl_errno($ch)){
      $exception=curl_error($ch);
      curl_close($ch);
      throw new \Exception($exception);
    }
    curl_close($ch);
    return $responseData;
  }

}