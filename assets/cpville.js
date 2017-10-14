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
          $(value).addClass("hide").addClass("hidden");
        }
        $(value).find("input[type=text]").prop("readonly",true).addClass("readonly");
      });
      var parent=$(this).parent();
      $(parent).css("position","relative");
      $(this).autocomplete({
        minLength: 1,
        appendTo: parent,
        position: {
          my : "left top",
          at: "left bottom",
          collision: "flipfit"
        },
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
        search: function (event, ui) {
            $(this).addClass('autocomplete-search');
        },
        open: function (event, ui) {
            $(this).removeClass('autocomplete-search');
        },
        change: function (event, ui) {
          if(!ui.item){
              $(this).val("");
              $(optionLines.join(",")).each(function( index ) {
                $(this).find("input[type=text]").val("").trigger('keyup').trigger('blur');
              });
          }
        },
        select: function( event, ui ) {
            $.each(ui.item, function(key, value) {
              $("input[type=text][name$='X"+qId+key+endLibel+"']").val(value).trigger('keyup').trigger('blur');
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
    });



  }else{
    $(optionLines.join(",")).each(function( index ) {
      $(this).show();
    });
  }
}
