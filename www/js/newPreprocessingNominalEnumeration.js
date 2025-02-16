//TODO obslužné skripty pro preprocessing nominal enumeration

/**
 * Akce pro odebrání konkrétní hodnoty z intervalu (bez reloadu)
 * @param event
 */
var removeValueAction = function(event){
  event.preventDefault();
  $(this).parent('.value').remove();
};

/**
 * Akce pro odebrání values binu (bez reloadu)
 * @param event
 */
var removeBinAction = function(event){
  event.preventDefault();
  $(this).parent('.valuesBin').remove();
};


var showSelectSubform = function(event){
  event.preventDefault();
  prepareSelectSubform($(this).parent());
};

var hideSelectSubform = function(event,element){
  event.preventDefault();
  if (typeof element === "undefined"){
    element=$(this);
  }
  prepareShowSelectSubformButton(element.closest('.addValue'));
};
/**
 * Funkce pro vygenerování HTML kódu s tlačítkem pro zobrazení subformu se selectem
 * @param parentElement
 */
var prepareShowSelectSubformButton = function(parentElement){
  parentElement.html($('<input type="button" />').val(localization['add_value']).click(showSelectSubform));
};

/**
 * Akce pro odeslání subformu se selectem
 * @param event
 */
var submitSelectSubform = function(event){
  event.preventDefault();
  var value=$(this).closest('.addValue').find('select').val();

  var parentElement=$(this).closest('.valuesBin');
  //vyřešení základní částí jména (dle existujících hodnot)
  var newElementNameBase='';
  parentElement.children('input[type="text"]').each(function(){
    var name=$(this).attr('name');
    var nameArr=name.split('[');
    newElementNameBase=nameArr[0]+'['+nameArr[1];
  });

  var maxId=0;
  parentElement.find('.value input[type="text"]').each(function(){
    var name=$(this).attr('name');
    var nameArr=name.split('[');
    maxId=Math.max(maxId,parseInt(nameArr[3]));
  });
  newElementNameBase+='[values]['+(maxId+1)+']';
  //vytvoření příslušné části html kódu
  var newElementHtml=$('<span class="value"></span>');
  newElementHtml.append($('<input type="text" readonly="readonly" name="'+newElementNameBase+'[value]" />').attr('value',value));
  newElementHtml.append($('<strong></strong>').text(value));
  var button = $('<input type="submit" value="x" name="'+newElementNameBase+'[remove]" class="removeValue" formnovalidate="" />');
  button.click(removeValueAction);
  newElementHtml.append(button);
  parentElement.find('.values').append(newElementHtml," ");

  hideSelectSubform(event,$(this));
};

var prepareSelectSubform = function(parentElement){
  //kontrola, jaké hodnoty je možné přidat
  var usedValues = [];
  var selectItemsValues = [];
  $('.valuesBin .value input[type="text"]').each(function(){
    usedValues.push($(this).val());
  });

  $.each(usableValues,function(i,value){
    if (usedValues.indexOf(value)==-1){
     selectItemsValues.push(value);
    }
  });

  var html = '<table><tr><td><label>'+localization['add_value']+':</label></td>';//TODO id inputu
  html +='<td class="selectTd"></td>';
  html +='<td><input type="button" class="submit" /> <input type="button" class="storno" /></td></table>';
  parentElement.html(html);
  parentElement.find('input.submit').val(localization['add_value']).click(submitSelectSubform);
  parentElement.find('input.storno').val(localization['storno']).click(hideSelectSubform);

  //přidání položek do selectu...
  var selectTd = parentElement.find('td.selectTd');
  if (selectItemsValues.length>0){
    var select = $('<select></select>');
    $.each(selectItemsValues,function(i,value){
      select.append($('<option>', {
        value: value,
        text : value
      }));
    });
    selectTd.append(select);
  }else{
    selectTd.append('<span class="stateErr">'+localization['no_values']+'</span>');
  }

  select.focus();
};

$(document).ready(function (){
  //připojení eventů k odesílacím tlačítkům (aby se zbytečně nepřenačítala stránka)
  $('.valuesBin input.removeValue').click(removeValueAction);
  $('.valuesBin input.removeBin').click(removeBinAction);


  if (typeof(usableValues)!="undefined" && usableValues.length>0){
    //máme k dispozici výčet hodnot
    $('.valuesBin .addValue').each(function(){
      prepareShowSelectSubformButton($(this));
    });
    ////$('.valuesBin .addValue').css('border','1px solid red');
  }else{
    //TODO doplnění dalších variant...

  }


});

