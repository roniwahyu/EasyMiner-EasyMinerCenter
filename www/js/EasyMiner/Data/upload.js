/**
 * Skripty pro upload dat
 */
'use strict';

var DataUpload=function(){
  this.fileInputElement=null;
  this.jqElements={
    flashMessages: null,

    uploadFormBlock:    null,
    uploadConfigBlock:  null,
    uploadConfigPreviewBlock: null,
    uploadColumnsBlock: null,
    uploadProgressBlock:null,
    uploadProgressBar:  null,
    uploadColumnsListBlock:null,

    databaseTypeInput: null,
    tableNameInput: null,
    escapeCharacterInput: null,
    delimiterInput: null,
    nullValueInput: null,
    encodingInput: null,
    enclosureInput: null,
    localeInput: null
  };
  this.dataServiceUrl=null;
  this.apiKey=null;
  this.previewUrl=null;
  this.uploadPreviewDataUrl=null;
  /**
   * @type {FileUploader}
   */
  var fileUploader;
  /**
   * Velikost nahrávaných dat při potřebě získání ukázky
   * @type {number}
   */
  const UPLOAD_PREVIEW_BYTES=300000;
  /**
   * Identifikace nahraného souboru pro generování ukázky...
   * @type {string}
   */
  var previewFileName='';
  var columnNames=[];
  var columnDataTypes=[];
  var previewRows=[];
  var self=this;

  /**
   * Funkce vracející vyplněnou URL pro načtení náhledu
   * @returns {string}
   */
  var getPreviewUrl=function(reloadParams){
    var result=self.previewUrl;
    var inputParams=self.getInputParams();
    result=result
      .replace('__FILE__',encodeURIComponent(previewFileName))
      .replace('__ENCODING__',encodeURIComponent(inputParams.encoding))
      .replace('__ENCLOSURE__',encodeURIComponent(inputParams.quotesChar))
      .replace('__ESCAPE_CHARACTER__',encodeURIComponent(inputParams.escapeChar))
      .replace('__NULL_VALUE__',encodeURIComponent(inputParams.nullValues[0]))
      .replace('__LOCALE__',encodeURIComponent(inputParams.locale));
    //pokud by mělo dojít k přenačtení parametrů, tak nebudeme uvádět následující parametry
    if (reloadParams==true){
      result=result.replace('__DELIMITER__','');
    }else{
      result=result.replace('__DELIMITER__',encodeURIComponent(inputParams.separator));
    }
    return result;
  };

  /**
   * Funkce pro zobrazení bloku s nahrávacím formulářem
   */
  this.showUploadFormBlock=function(){
    this.jqElements.uploadConfigBlock.hide();
    this.jqElements.uploadColumnsBlock.hide();
    this.jqElements.uploadProgressBlock.hide();
    this.jqElements.uploadFormBlock.show();
  };
  /**
   * Funkce pro inicializaci konfigurace uploadu
   */
  this.showUploadConfigBlock=function(){
    //skrytí případných flash zpráv
    this.jqElements.flashMessages.hide();
    //zobrazení potřebných bloků
    this.jqElements.uploadFormBlock.hide();
    this.jqElements.uploadColumnsBlock.hide();
    this.jqElements.uploadProgressBlock.hide();
    this.jqElements.uploadConfigBlock.show();
  };

  /**
   * Funkce pro vygenerování formuláře pro konfiguraci datových sloupců a jeho zobrazení
   */
  this.showColumnsConfigBlock=function(){
    //připravení položek příslušného formuláře...
    var listBlock=this.jqElements.uploadColumnsListBlock;
    var listBlockTable=$('<table><tr><th>'+'Column name'+'</th><th>'+'Data type'+'</th><th>'+'Values from first rows...'+'</th></tr></table>');
    for (var i in columnNames){
      //položka konkrétního sloupce
      var columnDetailsTr=$('<tr></tr>');
      var nameInput=$('<input type="text" id="column_'+i+'_name" required />').val(columnNames[i]);//TODO připojení kontrol na tvar názvů sloupců
      columnDetailsTr.append($('<td></td>').html(nameInput));
      var dataTypeSelect=$('<select id="column_'+i+'_type"><option value="nominal">nominal [ABC]</option><option value="numerical">numerical [123]</option><option value="null">--ignore--</option></select>');
      dataTypeSelect.val(columnDataTypes[i]);
      columnDetailsTr.append($('<td></td>').html(dataTypeSelect));
      var valuesTd=$('<td class="values"></td>');
      //náhled hodnot
      var previewedRows=0;
      var previewData=[];
      var valuesTdDiv=$('<div></div>');
      valuesTd.append(valuesTdDiv);
      for (var row in previewRows){
        if (previewedRows>10){break;}
        var rowData=previewRows[row];
        if (rowData.hasOwnProperty(i)){
          valuesTdDiv.append($('<span></span>').text(rowData[i]));
        }
      }
      columnDetailsTr.append(valuesTd);
      listBlockTable.append(columnDetailsTr);
    }
    listBlock.html(listBlockTable);//přiřazení tabulky do příslušného obsahu
    //zobrazení potřebných bloků
    this.jqElements.uploadFormBlock.hide();
    this.jqElements.uploadProgressBlock.hide();
    this.jqElements.uploadConfigBlock.hide();
    this.jqElements.uploadColumnsBlock.show();
  };

  /**
   * Funkce pro upload ukázky dat
   */
  this.reloadPreview=function(reloadParams){
    if (previewFileName==""){
      this.uploadPreviewData();
      return;
    }

    $.getJSON(getPreviewUrl(reloadParams),function(data){
      //nastavení příslušných proměnných
      columnNames=data['columnNames'];
      columnDataTypes=data['dataTypes'];
      previewRows=data['data'];
      //nastavení hodnot ve formuláři dle vyhodnocení na serveru
      self.jqElements.delimiterInput.prop('data-old-value',data.config.delimiter);
      self.jqElements.delimiterInput.val(data.config.delimiter);
      self.jqElements.enclosureInput.prop('data-old-value',data.config.enclosure);
      self.jqElements.enclosureInput.val(data.config.enclosure);
      self.jqElements.encodingInput.prop('data-old-value',data.config.encoding);
      self.jqElements.encodingInput.val(data.config.encoding);
      self.jqElements.escapeCharacterInput.prop('data-old-value',data.config.escapeCharacter);
      self.jqElements.escapeCharacterInput.val(data.config.escapeCharacter);
      self.jqElements.localeInput.prop('data-old-value',data.config.locale);
      self.jqElements.localeInput.val(data.config.locale);
      self.jqElements.nullValueInput.prop('data-old-value',data.config.nullValue);
      self.jqElements.nullValueInput.val(data.config.nullValue);

      if (reloadParams){
        self.showUploadConfigBlock();
      }
      //vypsání hodnot sloupců
      var previewTable=$('<table></table>');
      var columnsCount=0;
      //názvy sloupců
      var tr=$('<tr></tr>');
      for (var columnI in columnNames){
        var th=$('<th></th>');
        th.text(columnNames[columnI]);
        tr.append(th);
        columnsCount++;
      }
      previewTable.append(tr);
      //hodnoty jednotlivých řádků
      for (var rowI in previewRows){
        var tr=$('<tr></tr>');
        for (var columnI=0;columnI<columnsCount;columnI++){
          var td=$('<td></td>');
          td.text(previewRows[rowI][columnI]);
          tr.append(td);
        }
        previewTable.append(tr);
      }
      self.jqElements.uploadConfigPreviewBlock.html(previewTable);
    });
  };

  /**
   * Funkce pro nahrání celého souboru
   */
  this.submitAllData=function(){
    //TODO odeslání všech potřebných dat
    //inicializace file uploaderu
    fileUploader.onUploadStart=function(){
      //zobrazení upload bloku a spuštění uploadu
      self.jqElements.uploadFormBlock.hide();
      self.jqElements.uploadColumnsBlock.hide();
      self.jqElements.uploadConfigBlock.hide();
      self.jqElements.uploadProgressBlock.show();
    };
    fileUploader.onUploadFinished=function(){
      //TODO redirect...
      console.log('upload finished -> go to new miner creation...');
    };
    fileUploader.onProgressUpdate=function(){
      var progressState=fileUploader.getProgressState();
      //TODO doplnění pozadí u progress baru
      self.jqElements.uploadProgressBar.text(Math.round(progressState*100)+'%');
    };
    fileUploader.startUpload('upload',this.getInputParams());
  };

  /**
   * Funkce pro sestavení parametrů pro upload dat...
   * @returns {{name: string, mediaType: string, dbType: string, separator: string, encoding: string, quotesChar: string, escapeChar: string, locale: string, nullValues: string[], dataTypes: string[]}}
   */
  this.getInputParams=function(){
    var result={
      "name": "testFile",//TODO odkud bude brán název souboru?
      "mediaType": "csv",
      "dbType": this.jqElements.databaseTypeInput.val(),
      "separator": this.jqElements.delimiterInput.val(),
      "encoding": this.jqElements.encodingInput.val(),
      "quotesChar": this.jqElements.enclosureInput.val(),
      "escapeChar": this.jqElements.escapeCharacterInput.val(),
      "locale": this.jqElements.localeInput.val(),
      nullValues: [this.jqElements.nullValueInput.val()],
      columnNames: columnNames,
      dataTypes: columnDataTypes
    };
    var compression=false;/*TODO vyřešení, jestli je příslušný soubor komprimovaný*/
    if (compression){
      result['compression']='zip';
    }
    return result;
  };

  /**
   * Funkce pro upload ukázkových dat
   */
  this.uploadPreviewData=function(){
    //region upload ukázkových dat
    var fileReader=new FileReader();
    fileReader.onload=function(){
      jQuery.ajax(self.uploadPreviewDataUrl,{
        type: 'post',
        data: fileReader.result,
        contentType: 'text/plain',
        dataType: 'json',
        success: function(result){
          //soubor byl nahrán, chceme zobrazit ukázku dat...
          previewFileName=result.file;
          self.reloadPreview(true);
        },
        error: function(xhr, status, error){
          //došlo k chybě - zobrazíme info o chybě a následně stornujeme posílání
          //FIXME zpráva o chybě...
        }
      });
    };
    fileReader.onerror=function(){
      //FIXME zpráva o chybě při přístupu k souboru...
    };
    var file=this.fileInputElement.files[0];
    fileReader.readAsBinaryString(file.slice(0,UPLOAD_PREVIEW_BYTES));
    //endregion
  };


  //region init
  /**
   * Kompletní spuštění inicializačních funkcí této JS komponenty
   */
  this.init=function(){
    initUploadFormActions();
    initUploadConfigFormActions();
    initColumnsConfigFormActions();
    initFileUploader();
    //zobrazíme první část uploadu
    self.showUploadFormBlock();
  };

  /**
   * Funkce pro inicializaci nahrávacího formuláře
   */
  var initUploadFormActions=function(){
    var form=self.jqElements.uploadFormBlock.find('form');
    form.addClass('ajax');
    form.submit(function(e){
      e.preventDefault();
      e.stopPropagation();
      if (Nette.validateForm(this)){
        //načteme preview, posléze dojde k zobrazení konfigurace importu
        self.reloadPreview(true);
      }
    });
  };

  /**
   * Funkce inicializující formulář pro konfiguraci parametrů uploadu
   */
  var initUploadConfigFormActions=function(){
    var form=self.jqElements.uploadConfigBlock.find('form');
    var changeFunction=function(){
      //změna hodnoty inputu (jeho opuštění)
      if (!$(this).hasClass(LiveForm.options.controlErrorClass)){
        if ($(this).prop('data-old-value')!=$(this).val()){
          $(this).prop('data-old-value',$(this).val());
          self.reloadPreview();
        }
      }
    };
    form.find('select').change(changeFunction);
    form.find('input[type="text"]').blur(changeFunction);
    form.submit(function(e){
      e.preventDefault();
      e.stopPropagation();
      if(Nette.validateForm(this)){
        //zobrazíme konfiguraci jednotlivých sloupců...
        self.showColumnsConfigBlock();
      }
    });
    form.addClass('ajax');
  };

  /**
   * Funkce pro inicializaci formuláře pro konfiguraci sloupců
   */
  var initColumnsConfigFormActions=function(){
    var form=self.jqElements.uploadColumnsBlock.find('form');
    form.addClass('ajax');
    form.submit(function(e){
      e.preventDefault();
      e.stopPropagation();
      //TODO kontrola názvů sloupců...
      Nette.validateForm(this);
      //XXX self.submitAllData();
    })
  };

  /**
   * Funkce pro inicializaci file uploaderu
   */
  var initFileUploader=function(){
    fileUploader=new FileUploader({
      dataServiceUrl:self.dataServiceUrl,
      apiKey:self.apiKey,
      inputElementId:self.fileInputElement.id,
      onProgressUpdate:function(){//TODO
        console.log('progress update..');
      },/*TODO
       onShowMessage: function(){

       },*/
    });
  }
  //endregion init
};


$(document).ready(function(){
  //inicializace celé aplikace
  var dataUpload=new DataUpload();
  var uploadFormBlock=$('#uploadFormBlock');
  var uploadConfigBlock=$('#uploadConfigBlock');
  var uploadProgressBlock=$('#uploadProgressBlock');
  dataUpload.fileInputElement=uploadFormBlock.find('form input[type="file"]').get(0);
  $('form').addClass('ajax');

  dataUpload.jqElements={
    uploadFormBlock:    uploadFormBlock,

    uploadConfigBlock:  uploadConfigBlock,
    uploadConfigPreviewBlock: uploadConfigBlock.find('#uploadConfigPreviewBlock'),

    uploadColumnsBlock: $('#uploadColumnsBlock'),
    uploadColumnsListBlock: $('#uploadColumnsListBlock'),

    uploadProgressBlock:uploadProgressBlock,
    uploadProgressBar:  uploadProgressBlock.find('.progressBar'),

    databaseTypeInput: uploadFormBlock.find('[name="dbType"]'),
    tableNameInput: null,//XXX
    escapeCharacterInput: uploadConfigBlock.find('[name="escape"]'),
    delimiterInput: uploadConfigBlock.find('[name="separator"]'),
    nullValueInput: uploadConfigBlock.find('[name="nullValue"]'),
    encodingInput: uploadConfigBlock.find('[name="encoding"]'),
    enclosureInput: uploadConfigBlock.find('[name="enclosure"]'),
    localeInput: uploadConfigBlock.find('[name="locale"]'),

    flashMessages: $('.flash')
  };

  dataUpload.dataServiceUrl=dataServiceUrl;
  dataUpload.apiKey=apiKey;
  dataUpload.previewUrl=previewUrl;
  dataUpload.uploadPreviewDataUrl=uploadPreviewDataUrl;
  dataUpload.init();
});