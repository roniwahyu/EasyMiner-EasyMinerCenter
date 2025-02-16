<?php

namespace EasyMinerCenter\EasyMinerModule\Components;

use EasyMinerCenter\Model\Data\Entities\DbColumnValuesStatistic;
use EasyMinerCenter\Model\Data\Facades\DatabasesFacade;
use EasyMinerCenter\Model\EasyMiner\Entities\DatasourceColumn;
use EasyMinerCenter\Model\EasyMiner\Entities\Format;
use EasyMinerCenter\Model\EasyMiner\Entities\MetaAttribute;
use EasyMinerCenter\Model\EasyMiner\Facades\DatasourcesFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\MetaAttributesFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\UsersFacade;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\ITemplate;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\ITranslator;

/**
 * Class MetaAttributesSelectControl
 * @package EasyMinerCenter\EasyMinerModule\Components
 * @method onComponentHide
 * @method onComponentShow
 */
class MetaAttributesSelectControl extends Control{

  /** @var  MetaAttributesFacade $metaAttributesFacade */
  private $metaAttributesFacade;
  /** @var  DatasourcesFacade $datasourcesFacade */
  private $datasourcesFacade;
  /** @var  ITranslator $translator */
  private $translator;
  /** @var UsersFacade $usersFacade */
  private $usersFacade;
  /** @var DatabasesFacade $databasesFacade */
  private $databasesFacade;

  /** @var callable[] $onComponentShow*/
  public  $onComponentShow=array();
  /** @var callable[] $onComponentHide*/
  public  $onComponentHide=array();

  /**
   * @param MetaAttributesFacade $metaAttributesFacade
   * @param DatasourcesFacade $datasourcesFacade
   * @param ITranslator $translator
   * @param UsersFacade $usersFacade
   * @param DatabasesFacade $databasesFacade
   */
  public function __construct(MetaAttributesFacade $metaAttributesFacade, DatasourcesFacade $datasourcesFacade, ITranslator $translator, UsersFacade $usersFacade, DatabasesFacade $databasesFacade){
    parent::__construct();
    $this->metaAttributesFacade=$metaAttributesFacade;
    $this->datasourcesFacade=$datasourcesFacade;
    $this->translator=$translator;
    $this->usersFacade=$usersFacade;
    $this->databasesFacade=$databasesFacade;
  }

  /**
   * Vykreslení komponenty (na základě zavolaného signálu)
   */
  public function render(){
    $template=$this->template;
    $template->render();
  }

  /**
   * Signál pro výběr metaatributu
   * @param int $datasource
   * @param int $column
   */
  public function handleSelectMetaAttribute($datasource,$column){
    $this->onComponentShow();
    $this->template->setFile(__DIR__ . '/selectMetaAttribute.latte');
    try{
      /** @noinspection PhpUndefinedFieldInspection */
      $this->template->datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$column);
      /** @noinspection PhpUndefinedFieldInspection */
      $this->template->metaAttributes=$this->metaAttributesFacade->findMetaAttributes();
    }catch (\Exception $e){
      $this->flashMessage($this->translator->translate('Ooops, some error...'),'error');
      $this->onComponentHide();
    }
  }



  /**
   * Signál pro výběr formátu z existujícího metaatributu
   * @param int $datasource
   * @param int $column
   * @param string $metaAttribute
   */
  public function handleSelectFormat($datasource,$column,$metaAttribute=null){
    $this->onComponentShow();
    $this->template->setFile(__DIR__ . '/selectFormat.latte');
    try{
      /** @noinspection PhpUndefinedFieldInspection */
      $this->template->datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$column);
    }catch (\Exception $e){
      $this->flashMessage($this->translator->translate('Ooops, some error...'),'error');
      $this->onComponentHide();
    }
    try{
      $metaAttribute=$this->metaAttributesFacade->findMetaAttribute($metaAttribute);
      /** @noinspection PhpUndefinedFieldInspection */
      $this->template->metaAttribute=$metaAttribute;
      /** @noinspection PhpUndefinedFieldInspection */
      $this->template->formats=$metaAttribute->formats;
    }catch (\Exception $e){
      $this->redirect('SelectMetaAttribute',array('datasource'=>$datasource,'column'=>$column));//při chybě při načítání metaatributu přesměrujeme uživatele zpátky na vytvoření metaatributu
    }
  }

  /**
   * Signál pro skrytí komponenty...
   */
  public function handleClose(){
    $this->onComponentHide();
  }

  /**
   * Signál pro zobrazení formuláře pro vytvoření nového metaatributu a formátu
   * @param $datasource
   * @param $column
   */
  public function handleNewMetaAttribute($datasource,$column){
    $this->onComponentShow();
    $this->template->setFile(__DIR__ . '/newMetaAttribute.latte');
    $datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$column);
    /** @noinspection PhpUndefinedFieldInspection */
    $this->template->datasourceColumn=$datasourceColumn;
    /** @var Form $form */
    $form=$this->getComponent('newMetaAttributeForm');
    $defaults=array(
      'datasource'=>$datasource,
      'column'=>$column,
      'metaAttributeName'=>$datasourceColumn->name
    );
    if ($datasourceColumn->type==DatasourceColumn::TYPE_STRING){
      /** @var SelectBox $formatType */
      $formatType=$form->getComponent('formatType');
      //$formatType->setValue('values');
      $defaults['formatType']='values';
      $form->setDefaults($defaults);
      $items=$formatType->items;
      unset($items['interval']);
      $formatType->items=$items;
      $formatType->setDisabled();
    }else{
      $form->setDefaults($defaults);
    }

  }

  /**
   * Signál pro zobrazení formuláře pro vytvoření nového metaatributu a formátu
   * @param int $datasource
   * @param int $column
   * @param string $metaAttribute
   */
  public function handleNewFormat($datasource,$column,$metaAttribute){
    $this->onComponentShow();
    $this->template->setFile(__DIR__ . '/newFormat.latte');
    $datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$column);
    /** @noinspection PhpUndefinedFieldInspection */
    $this->template->datasourceColumn=$datasourceColumn;

    $metaAttribute=$this->metaAttributesFacade->findMetaAttribute($metaAttribute);
    /** @noinspection PhpUndefinedFieldInspection */
    $this->template->metaAttribute=$metaAttribute;

    /** @var Form $form */
    $form=$this->getComponent('newFormatForm');
    $defaults=array(
      'datasource'=>$datasource,
      'column'=>$column,
      'metaAttributeName'=>$metaAttribute->name,
      'metaAttribute'=>$metaAttribute->metaAttributeId,
    );
    if ($datasourceColumn->type=='string'){
      /** @var SelectBox $formatType */
      $formatType=$form->getComponent('formatType');
      $defaults['formatType']='values';
      $items=$formatType->items;
      unset($items['interval']);
      $formatType->items=$items;
      $form->setDefaults($defaults);
      $formatType->setDisabled();
    }else{
      $form->setDefaults($defaults);
    }

  }

  /**
   * Signál pro propojení DatasourceColumn s Formátem
   * @param int $datasource
   * @param int $column
   * @param string $format
   */
  public function handleSetDatasourceColumnFormat($datasource,$column,$format){
    //TODO kontrola oprávnění!!!
    $datasource=$this->datasourcesFacade->findDatasource($datasource);
    $this->databasesFacade->openDatabase($datasource->getDbConnection());
    $datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$column);
    $datasourceColumnValuesStatistic=$this->databasesFacade->getColumnValuesStatistic($datasource->dbTable,$datasourceColumn->name);

    $format=$this->metaAttributesFacade->findFormat($format);
    $this->metaAttributesFacade->updateFormatFromDatasourceColumn($format,$datasourceColumn,$datasourceColumnValuesStatistic);
    $datasourceColumn->format=$format;
    $this->datasourcesFacade->saveDatasourceColumn($datasourceColumn);
  }


  /**
   * Formulář pro vytvoření nového metaatributu
   * @return Form
   */
  protected function createComponentNewMetaAttributeForm(){
    $form = new Form();
    $metaAttributeName=$form->addText('metaAttributeName','Meta-attribute name:')
      ->setRequired()
      ->setAttribute('class','normalWidth');
    $metaAttributeName->addRule(Form::MIN_LENGTH,'Min length of meta-attribute name is %s characters!',3);
    $metaAttributeName->addRule(function(TextInput $control){
      try{
        $metaAttribute=$this->metaAttributesFacade->findMetaAttributeByName($control->value);
        if ($metaAttribute instanceof MetaAttribute){
          return false;
        }
      }catch (\Exception $e){/*chybu ignorujeme (nenalezený metaatribut je OK)*/}
      return true;
    },'Meta-attribute with this name already exists!');
    $formatName=$form->addText('formatName','Format name:')->setRequired()->addRule(Form::MIN_LENGTH,'Min length of format name is %s characters!',3);
    $formatName->setAttribute('class','normalWidth');
    $form->addHidden('datasource');
    $form->addHidden('column');
    $form->addCheckbox('formatShared','Create shared (standard) format');
    $form->addSelect('formatType','Values range:',array('interval'=>'Continuous values (interval)','values'=>'Distinct values (enumeration)'))
    ->setAttribute('class','normalWidth');
    $submit=$form->addSubmit('create','Create meta-attribute');
    $submit->setValidationScope(array($metaAttributeName,$formatName));
    /*$form->onSubmit[]=function(Form $form){
      exit(var_dump($form->getErrors()));
    };*/
    $submit->onClick[]=function(SubmitButton $button){
      $values=$button->form->values;
      try{
        $datasource=$this->datasourcesFacade->findDatasource($values->datasource);
        $datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($datasource,$values->column);
        $this->databasesFacade->openDatabase($datasource->getDbConnection());
        $datasourceColumnValuesStatistics=$this->databasesFacade->getColumnValuesStatistic($datasource->dbTable,$datasourceColumn->name);
        $format=$this->createMetaAttributeFromDatasourceColumn($values->metaAttributeName,$values->formatName,$datasourceColumn,$datasourceColumnValuesStatistics,@$values->formatType,@$values->formatShared);
        $datasourceColumn->format=$format;
        $this->datasourcesFacade->saveDatasourceColumn($datasourceColumn);
      }catch (\Exception $e){
        $this->flashMessage($this->translator->translate('MetaAttribute creation failed.'));
      }
      $this->redirect('Close');
    };
    $storno=$form->addSubmit('storno','Storno');
    $storno->setValidationScope(array());
    $storno->onClick[]=function(SubmitButton $button){
      $values=$button->form->values;
      $this->redirect('SelectMetaAttribute',array('datasource'=>$values->datasource,'column'=>$values->column));
    };
    $form->onError[]=function($form){
      $values=$form->values;
      $this->handleNewMetaAttribute($values->datasource,$values->column);
    };
    return $form;
  }


  /**
   * Formulář pro vytvoření nového formátu
   * @return Form
   */
  protected function createComponentNewFormatForm(){
    $form = new Form();
    $form->addHidden('datasource');
    $form->addHidden('column');
    $metaAttribute=$form->addHidden('metaAttribute');
    $form->addText('metaAttributeName','Meta-attribute:')->setAttribute('readonly','readonly')->setAttribute('class','normalWidth');
    $formatName=$form->addText('formatName','Format name:')->setRequired()->setAttribute('class','normalWidth');
    $formatName->addRule(Form::MIN_LENGTH,'Min length of format name is %s characters!',3);
    $formatName->addRule(function(TextInput $control)use($metaAttribute){
      try{
        $format=$this->metaAttributesFacade->findFormatByName($metaAttribute->value,$control->value);///XXX
        if ($format instanceof Format){
          return false;
        }
      }catch (\Exception $e){/*chybu ignorujeme (nenalezený metaatribut je OK)*/}
      return true;
    },'Format with this name already exists!');
    $form->addCheckbox('formatShared','Create shared (standard) format');
    $form->addSelect('formatType','Values range:',array('interval'=>'Continuous values (interval)','values'=>'Distinct values (enumeration)'))->setAttribute('class','normalWidth')->setDefaultValue('values');
    $submit=$form->addSubmit('create','Create format');
    $submit->setValidationScope(array($formatName));
    $submit->onClick[]=function(SubmitButton $button){
      $values=$button->form->values;
      try{
        $datasourceColumn=$this->datasourcesFacade->findDatasourceColumn($values->datasource,$values->column);
        $metaAttribute=$this->metaAttributesFacade->findMetaAttribute($values->metaAttribute);

        $datasource=$this->datasourcesFacade->findDatasource($values->datasource);
        $this->databasesFacade->openDatabase($datasource->getDbConnection());
        $datasourceColumnValuesStatistic=$this->databasesFacade->getColumnValuesStatistic($datasource->dbTable,$datasourceColumn->name);
        $format=$this->metaAttributesFacade->createFormatFromDatasourceColumn($metaAttribute,$values->formatName,$datasourceColumn,$datasourceColumnValuesStatistic,@$values->formatType,@$values->formatShared);

        $datasourceColumn->format=$format;
        $this->datasourcesFacade->saveDatasourceColumn($datasourceColumn);
      }catch (\Exception $e){
        $this->flashMessage($this->translator->translate('Format creation failed.'));
      }
      $this->redirect('Close');
    };
    $storno=$form->addSubmit('storno','Storno');
    $storno->setValidationScope(array());
    $storno->onClick[]=function(SubmitButton $button){
      $values=$button->form->values;
      $this->redirect('SelectFormat',array('datasource'=>$values->datasource,'column'=>$values->column,'metaAttribute'=>$values->metaAttribute));
    };
    $form->onError[]=function(Form $form){
      $values=$form->values;
      $this->handleNewFormat($values->datasource,$values->column,$values->metaAttribute);
    };
    return $form;
  }

  /**
   * Funkce pro vytvoření metaatributu a formátu na základě hodnot datového sloupce
   * @param string $metaAttributeName
   * @param string $formatName
   * @param DatasourceColumn $datasourceColumn
   * @param DbColumnValuesStatistic $columnValuesStatistic
   * @param string $formatType
   * @param bool $formatShared
   * @return Format
   */
  private function createMetaAttributeFromDatasourceColumn($metaAttributeName,$formatName,DatasourceColumn $datasourceColumn, DbColumnValuesStatistic $columnValuesStatistic,$formatType,$formatShared=false){
    $metaAttribute=$this->metaAttributesFacade->findOrCreateMetaAttributeWithName($metaAttributeName);
    return $this->metaAttributesFacade->createFormatFromDatasourceColumn($metaAttribute,$formatName,$datasourceColumn,$columnValuesStatistic,$formatType,$formatShared,$this->presenter->getUser()->getId());
  }


  /**
   * @return ITemplate
   */
  public function createTemplate(){
    $template=parent::createTemplate();
    /** @noinspection PhpUndefinedMethodInspection */
    $template->setTranslator($this->translator);
    return $template;
  }


} 