
function autoCpVille(qId,options){
  /* Name of the input by sub question code : since LimeSurvey 7 the name of the input does not contain the code */
  var fields=options.fields || {};
  /* Get the line of a sub question by the code, empty if there is no such sub question */
  function getLine(code){
    if(!Object.prototype.hasOwnProperty.call(fields,code)){
      return $();
    }
    return $(document.getElementById("javatbd"+fields[code]));
  }
  var answerLibels=$();
  $.each(fields, function( code ) {
    if(code.indexOf(options.answerLibel)===0){
      answerLibels=answerLibels.add(getLine(code).find("input[type=text]").data("cpvillecode",code));
    }
  });
  if(answerLibels.length>=1)
  {
    $(answerLibels).keypress(function(e) {
         var code = e.keyCode || e.which;
          if (code == 9) {
            e.preventDefault();
          }
    });
    $(answerLibels).each(function(){
      $(this).attr('placeholder',options.placeholder);
      // Find final part
      var endLibel=$(this).data("cpvillecode").substring(options.answerLibel.length);
      // Set the options for each lines
      var optionLines=[];
      var optionShow=[];

      if(options.answerCp){
        optionLines.push(getLine(options.answerCp+endLibel));
        optionShow.push(options.showCp);
      }
      if(options.answerInsee){
        optionLines.push(getLine(options.answerInsee+endLibel));
        optionShow.push(options.showInsee);
      }
      if(options.answerNom){
        optionLines.push(getLine(options.answerNom+endLibel));
        optionShow.push(0);
      }
      if(options.otherColumns){
        $.each(options.otherColumns, function( index, value ) {
            optionLines.push(getLine(value+endLibel));
            optionShow.push(false);
        });
      }
      $.each(optionLines, function( index, value ) {
        if(!optionShow[index])
        {
          $(value).hide();
          /* hidden : bootstrap 3, d-none : bootstrap 5 (LimeSurvey 6 and later) */
          $(value).addClass("hidden d-none");
        }
        $(value).find("input[type=text]").prop("readonly",true).addClass("readonly");
      });
      var parent=$(this).parent();
      $(parent).css("position","relative");
      $(this).autocomplete({
            serviceUrl : options.jsonurl,
            dataType: "json",
            paramName: 'term',
            minChars: 2,
            autoSelectFirst:true,
            transformResult: function(responses) {
                return {
                    suggestions: $.map(responses, function(ville) {
                        return { value: ville.label, data: ville };
                    })
                };
            },
            onSearchStart: function(query) {
                $( this ).prop("readonly",true);
            },
            onSearchComplete : function(query, suggestions) {
                $( this ).prop("readonly",false);
            },
            onSelect : function(suggestion) {
                if(suggestion.data) {
                    $.each(suggestion.data, function(key, value) {
                        getLine(key+endLibel).find("input[type=text]").val(value).trigger('keyup');
                    });
                }
                if(suggestion.data.value == "") {
                    $(this).val("");
                }
            }
      });
    });
  }
}
