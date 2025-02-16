<?php

namespace EasyMinerCenter\InstallModule\DevModule\Presenters;
use EasyMinerCenter\InstallModule\DevModule\Model\Git;
use EasyMinerCenter\InstallModule\DevModule\Model\MysqlDump;
use EasyMinerCenter\InstallModule\Model\ConfigManager;
use EasyMinerCenter\InstallModule\Model\FilesManager;
use Nette\Application\Responses\TextResponse;

/**
 * Class DbDumpPresenter - presenter pro možnost exportu struktury databáze z vývojového serveru na GITHUB
 *
 * @package EasyMinerCenter\DevModule\Presenters
 * @author Stanislav Vojíř
 */
class DbPresenter extends BasePresenter{
  /** @var  ConfigManager $configManager */
  private $configManager;

  /**
   * Akce vracející strukturu databáze
   */
  public function actionReadStructure() {
    $mainDatabaseConfig=$this->configManager->data['parameters']['mainDatabase'];
    $dumpContent=MysqlDump::dumpStructureToFile($mainDatabaseConfig['host'],!empty($mainDatabaseConfig['port'])?$mainDatabaseConfig['port']:null,$mainDatabaseConfig['username'],$mainDatabaseConfig['password'],$mainDatabaseConfig['database']);
    $dump='';
    if (!empty($dumpContent)){
      foreach ($dumpContent as $row){
        $dump.=$row."\r\n";
      }
    }
    $this->sendResponse(new TextResponse($dump));
  }

  /**
   * Akce pro aktualizaci souboru s výpisem databáze
   */
  public function actionUpdateDumpFile() {
    #region export struktury databáze
    $mainDatabaseConfig=$this->configManager->data['parameters']['mainDatabase'];
    $dumpContent=MysqlDump::dumpStructureToFile($mainDatabaseConfig['host'],!empty($mainDatabaseConfig['port'])?$mainDatabaseConfig['port']:null,$mainDatabaseConfig['username'],$mainDatabaseConfig['password'],$mainDatabaseConfig['database']);
    $dump='';
    if (!empty($dumpContent)){
      foreach ($dumpContent as $row){
        $dump.=$row."\r\n";
      }
    }
    //uložení obsahu
    MysqlDump::saveSqlFileContent($dump);
    #endregion export struktury databáze
    $this->sendJson(['state'=>'OK']);
  }

  /**
   * Akce pro export struktury databáze a jeho kontrola oproti uloženému souboru
   * @throws \Nette\Application\AbortException
   */
  public function actionUpdateOnGit() {
    $message='';
    if (!MysqlDump::checkRequirements($message)){
      $this->sendJson(['state'=>'error','message'=>$message]);
      return;
    }

    #region reset git repository
    $sudoCredentials=$this->devConfigManager->getSudoCredentials();
    $git = new Git(FilesManager::getRootDirectory(),@$sudoCredentials['username'],@$sudoCredentials['password']);
    $git->reset();
    $git->clean();
    $git->pull();
    #endregion

    #region export struktury databáze
    $mainDatabaseConfig=$this->configManager->data['parameters']['mainDatabase'];
    $dumpContent=MysqlDump::dumpStructureToFile($mainDatabaseConfig['host'],!empty($mainDatabaseConfig['port'])?$mainDatabaseConfig['port']:null,$mainDatabaseConfig['username'],$mainDatabaseConfig['password'],$mainDatabaseConfig['database']);
    $dump='';
    if (!empty($dumpContent)){
      foreach ($dumpContent as $row){
        $dump.=$row."\r\n";
      }
    }
    if ($dump==MysqlDump::getExistingSqlFileContent()){
      $this->sendJson(['state'=>'OK','message'=>'Files are identical.']);
      return;
    }
    //uložení obsahu
    MysqlDump::saveSqlFileContent($dump);
    #endregion export struktury databáze
    #region commit
    $git->addFileToCommit(realpath(__DIR__.'/../../data/mysql.sql'));
    $git->commitAndPush('DB structure updated');
    #endregion commit

    $this->sendJson(['state'=>'OK','message'=>'Updated.']);
  }


  /**
   * Konstruktor, který zároveň získává přístup k lokální konfiguraci
   */
  public function __construct() {
    parent::__construct();
    $this->configManager=new ConfigManager(FilesManager::getRootDirectory().'/app/config/config.local.neon');
  }

}