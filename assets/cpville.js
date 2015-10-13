function autoCpVille(qId,options){
  var optionLines=[];
  var optionShow=[];

  if(options.answerCp){optionLines.push("#question"+qId+" li[id$='X"+qId+options.answerCp+"']");optionShow.push(options.showCp);}
  if(options.answerInsee){optionLines.push("#question"+qId+" li[id$='X"+qId+options.answerInsee+"']");optionShow.push(options.showInsee);}
  if(options.answerNom){optionLines.push("#question"+qId+" li[id$='X"+qId+options.answerNom+"']");optionShow.push(0);}
  
  $(optionLines.join(",")).each(function( index ) {
      console.log(optionShow[index]);
    if(!optionShow[index])
        $(this).hide();
    $(this).find("input[type=text]").prop("readonly",true).addClass("readonly");
  });
  var answerLibel=$("#question"+qId+" input[name$='X"+qId+options.answerLibel+"']");
  if(answerLibel.length==1)
  {
    $("#question"+qId+" li[id$='X"+qId+options.answerLibel+"']").addClass('libelle');
    $(answerLibel).keypress(function(e) {
         var code = e.keyCode || e.which;
          if (code == 9) { 
            e.preventDefault(); 
          } 
    });
    var cache = {};
    $(answerLibel).autocomplete({
      minLength: 1,
      source: function(request, response) {
          $.ajax({
              url: options.jsonurl,
              dataType: "json",
              data: {
                  term : request.term
              },
              success: function(data) {
                  response(data);
              }
          });
      },
      change: function (event, ui) {
        if(!ui.item){
            $(answerLibel).val("");
            $(optionLines.join(",")).each(function( index ) {
              $(this).find("input[type=text]").val("").trigger('keyup').trigger('blur');
            });
        }
      },
      select: function( event, ui ) {
          $.each(ui.item, function(key, value) {
            $("input[type=text][name$='X"+qId+key+"']").val(value).trigger('keyup').trigger('blur');
          });
      },
      focus: function (event, ui) {
        return false;
      },
      blur: function (event, ui) {
        $(this).trigger("change");
        return false;
      }
    });
  }else{
    $(optionLines.join(",")).each(function( index ) {
      $(this).shown();
    });
  }
}
