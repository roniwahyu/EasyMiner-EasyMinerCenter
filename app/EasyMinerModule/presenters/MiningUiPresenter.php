<?php
namespace EasyMinerCenter\EasyMinerModule\Presenters;

use EasyMinerCenter\Model\EasyMiner\Entities\Miner;
use EasyMinerCenter\Model\EasyMiner\Facades\DatasourcesFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\RuleSetsFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\UsersFacade;
use EasyMiner\MiningUI\Integration as MiningUIIntegration;
use IZI\IZIConfig;
use IZI\Parser\DataParser;
use Nette\Application\ForbiddenRequestException;
use Nette\Utils\Strings;

/**
 * Class MiningUiPresenter - presenter obsahující funkcionalitu vyžadovanou javascriptovým uživatelským rozhraním (migrace PHP kódu z projektu EasyMiner2)
 * @author Stanislav Vojíř
 * @package EasyMinerCenter\EasyMinerModule\Presenters
 */
class MiningUiPresenter extends BasePresenter{
  use MinersFacadeTrait;
  use ResponsesTrait;

  private $lang='en';//TODO předávání jazyka rozhraní
  /** @var  IZIConfig $config */
  private $config;
  /** @var  DatasourcesFacade $datasourcesFacade*/
  private $datasourcesFacade;
  /** @var  RuleSetsFacade $ruleSetsFacade */
  private $ruleSetsFacade;
  /** @var  UsersFacade $usersFacade */
  private $usersFacade;

  /**
   * Akce vracející data description a konfiguraci pro EasyMiner UI
   * @param int $id_dm
   * @param int $miner
   * @throws ForbiddenRequestException
   */
  public function actionGetData($id_dm,$miner){
    if (empty($miner)){
      $miner=$id_dm;
    }

    //------------------------------------------------------------------------------------------------------------------
    $minerType=Miner::DEFAULT_TYPE;
    if ($miner){
      $miner=$this->findMinerWithCheckAccess($miner);
      $minerType=$miner->type;
    }
    $FLPathElement='FLPath_'.Strings::upper($minerType);

    //------------------------------------------------------------------------------------------------------------------
    #region připravení informací pro UI - s odděleným připravením DataDictionary
    $dataDescriptionPMML=null;
    $dataParser = new DataParser($dataDescriptionPMML, $this->config->$FLPathElement, $this->config->FGCPath, null, null, $this->lang);
    $dataParser->loadData();
    $responseContent = $dataParser->parseData();

    $metasource=null;
    try{
      $metasource=$miner->metasource;
    }catch (\Exception $e){/*chybu ignorujeme - zatím pravděpodobně neexistují žádné atributy*/}

    $responseContent['DD']=$this->datasourcesFacade->exportDictionariesArr($miner->datasource,$metasource);
    #endregion připravení informací pro UI - s odděleným připravením DataDictionary

    uksort($responseContent['DD']['transformationDictionary'],function($a,$b){
      return strnatcasecmp($a,$b);
    });
    uksort($responseContent['DD']['dataDictionary'],function($a,$b){
      return strnatcasecmp($a,$b);
      //return strnatcasecmp(mb_strtolower($a,'utf-8'),mb_strtolower($b,'utf-8'));
    });

    $responseContent['status'] = 'ok';
    $responseContent['miner_type'] = $miner->type;
    $responseContent['miner_name'] = $miner->name;

    if ($miner->ruleSet){
      $ruleSet=$miner->ruleSet;
    }else{
      $ruleSet=$this->ruleSetsFacade->saveNewRuleSetForUser($miner->name,$this->usersFacade->findUser($this->user->id));
      $miner->ruleSet=$ruleSet;
      $this->minersFacade->saveMiner($miner);
    }

    $responseContent['miner_ruleset'] = ['id'=>$ruleSet->ruleSetId, 'name'=>$ruleSet->name];
    $responseContent['miner_config'] = $miner->getExternalConfig();

    $this->sendJsonResponse($responseContent);
  }


  /**
   * Akce pro zobrazení EasyMiner-MiningUI
   */
  public function renderDefault($id_dm,$miner) {
    if (empty($miner)){
      $miner=$id_dm;
    }

    $miner=$this->findMinerWithCheckAccess($miner);
    $this->template->miner=$miner;

    $this->template->javascriptFiles = MiningUIIntegration::$javascriptFiles;
    $this->template->cssFiles = MiningUIIntegration::$cssFiles;
  }
  

  #region injections
  /**
   * @param IZIConfig $iziConfig
   */
  public function injectIZIConfig(IZIConfig $iziConfig){
    $this->config=$iziConfig;
  }
  /**
   * @param DatasourcesFacade $datasourcesFacade
   */
  public function injectDatasourcesFacade(DatasourcesFacade $datasourcesFacade){
    $this->datasourcesFacade=$datasourcesFacade;
  }
  /**
   * @param RuleSetsFacade $ruleSetsFacade
   */
  public function injectRuleSetsFacade(RuleSetsFacade $ruleSetsFacade){
    $this->ruleSetsFacade=$ruleSetsFacade;
  }
  /**
   * @param UsersFacade $usersFacade
   */
  public function injectUsersFacade(UsersFacade $usersFacade){
    $this->usersFacade=$usersFacade;
  }
  #endregion
} 