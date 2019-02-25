function autoCpVille(qId,options){
  var answerLibels=$("#question"+qId+" input[name*='X"+qId+options.answerLibel+"']");
  if(answerLibels.length>=1)
  {
    $(answerLibels).keypress(function(e) {
         var code = e.keyCode || e.which;
          if (code == 9) {
            e.preventDefault();
          }
    });
    var cache = {};
    var baseLibel='X'+qId+options.answerLibel;
    var baseLibelLength=baseLibel.length;
    $(answerLibels).each(function(){
      $(this).attr('placeholder',options.placeholder);
      // Find final part
      var thisid=$(this).attr("id");
      var n=thisid.indexOf('X'+qId+options.answerLibel);
      var endLibel=$(this).attr("id").substring($(this).attr("id").indexOf(baseLibel)+baseLibelLength);
      // Set the options for each lines
      var optionLines=[];
      var optionShow=[];

      if(options.answerCp){
        optionLines.push("#question"+qId+" .answer-item[id$='X"+qId+options.answerCp+endLibel+"']");
        optionShow.push(options.showCp);
      }
      if(options.answerInsee){
        optionLines.push("#question"+qId+" .answer-item[id$='X"+qId+options.answerInsee+endLibel+"']");
        optionShow.push(options.showInsee);
      }
      if(options.answerNom){
        optionLines.push("#question"+qId+" .answer-item[id$='X"+qId+options.answerNom+endLibel+"']");
        optionShow.push(0);
      }
      $.each(optionLines, function( index, value ) {
        if(!optionShow[index])
        {
          $(value).hide();
          $(value).addClass("hidden");
        }
        $(value).find("input[type=text]").prop("readonly",true).addClass("readonly");
      });
      var parent=$(this).parent();
      $(parent).css("position","relative");
      $(this).autocomplete({
            serviceUrl : options.jsonurl,
            dataType: "json",
            paramName: 'term',
            minChars: 1,
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
                        $("input[type=text][name$='X"+qId+key+endLibel+"']").val(value).trigger('keyup');
                    });
                }
            }
      });
    });
  }else{
    $(optionLines.join(",")).each(function( index ) {
      $(this).show();
    });
  }
}
