define(["jquery", "mod_solo/definitions", "mod_solo/conversationconstants", "mod_solo/vtthelper","mod_solo/conversationset","mod_solo/playerhelper","core/templates"],
    function($, def, constants, vtthelper, transcriptionset, playerhelper,templates) {

    //pooodlltranscription helper is about the transcription tiles and editing

  return {
      controls: {},
      currentindex: false,
      currentitemcontainer: null,
      editoropen: false,
      firstrender: true,

      //set up the transcription edit session
      init: function(transcriptiondata,mediatype){
          transcriptionset.init(transcriptiondata);
          playerhelper.init(mediatype);
          this.initControls();
          this.initTiles();
          this.initEvents();
      },

      //set up our internal references to the elements on the page
      initControls: function(){
          this.controls.container = $("#poodllconvedit_tiles");
          this.controls.editor = $("#poodllconvedit_editor");
          this.controls.number = $("#poodllconvedit_editor .numb_song");
          this.controls.edpart = $("#" + def.C_EDITFIELD);
          this.controls.buttondelete = $("#" + def.C_BUTTONDELETE);
          this.controls.buttonmoveup = $("#" + def.C_BUTTONMOVEUP);
          this.controls.buttonmovedown = $("#" + def.C_BUTTONMOVEDOWN);
          this.controls.buttonapply = $("#" + def.C_BUTTONAPPLY);
          this.controls.buttoncancel = $("#" + def.C_BUTTONCANCEL);
          this.controls.buttonaddnew = $("#poodllconvedit_addnew");
;
      },

      hideEditor: function(){
          this.controls.editor.detach();
          this.controls.editor.hide();
          this.editoropen=false;
      },

      restoreTile: function(){
         var item = transcriptionset.fetchItem(this.currentindex);
         var that=this;

          var onend = function(tile){
              that.hideEditor();
              that.currentitemcontainer.append(tile);
          };
         this.fetchNewTextTile(this.currentindex,item.part, onend);

      },

      editorToTile: function(controls,currentindex,currentitemcontainer, onend){
          var part = $(controls.edpart).val();
          transcriptionset.updateItem(currentindex,part);
          this.doSave();

          var that = this;
          var on_fetch_finish = function(tile){
              //occasionally two event handlers will fire this and we get two tiles in one
              //this is an ugly check to prevent that
              if(!that.editoropen){return;}

              that.hideEditor();
              currentitemcontainer.append(tile);
              $(currentitemcontainer).removeClass('warning');
              if(onend){
                  onend();
              }
          };
          this.fetchNewTextTile(currentindex,part, on_fetch_finish);
          return true;

      },

      //attach events to the elements on the page
      initEvents: function(){
          var that = this;
          //this attaches event to classes of poodllconvedit_tt in "container"
          //so new items(created at runtime) get events by default
          this.controls.container.on("click",'.poodllconvedit_tt',function(){
              var newindex = parseInt($(this).parent().attr('data-id'));
              var theparent = $(this).parent();
              var do_next_tile_edit = function(){
                  that.currentindex = newindex;
                  that.currentitemcontainer = theparent;
                  that.shiftEditor(that.currentindex ,that.currentitemcontainer);
              }
              //save current
              if(that.editoropen === true){

                  that.editorToTile(that.controls,that.currentindex,that.currentitemcontainer, do_next_tile_edit);
              }else{
                  do_next_tile_edit();
              }


           });

          //editor button delete tile click event
          this.controls.container.on("click",'#' + def.C_BUTTONDELETE,function(){
          //this.controls.buttondelete.click(function(){
              result = confirm('Warning! This tile is going to be deleted!');
              if (result) {
                that.restoreTile();
                transcriptionset.removeItem(that.currentindex);
                that.syncFrom(that.currentindex);

              } else {
                  return;
              }

          });

          //editor button merge with prev tile click event
          this.controls.container.on("click",'#' + def.C_BUTTONMOVEUP,function(){
              var onend = function(){
                  transcriptionset.moveup(that.currentindex);
                  that.syncFrom(that.currentindex-1);
                  that.doSave();
              }
              that.editorToTile(that.controls,that.currentindex,that.currentitemcontainer, onend);


          });

          //editor button split current tile click event
          this.controls.container.on("click",'#' + def.C_BUTTONMOVEDOWN,function(){
              var onend = function(){
                  transcriptionset.movedown(that.currentindex);
                  that.syncFrom(that.currentindex);
                  that.doSave();
              }
              that.editorToTile(that.controls,that.currentindex,that.currentitemcontainer, onend);

          });


          //editor button apply changesclick event
          this.controls.container.on("click",'#' + def.C_BUTTONAPPLY,function(){
              that.editorToTile(that.controls,that.currentindex,that.currentitemcontainer);
          });

          //if the user clicks out of the editor (probably by clicking the form "next button)
          //editor button apply changesclick event
          this.controls.container.on("focusout",function(){
              that.editorToTile(that.controls,that.currentindex,that.currentitemcontainer);
          });


          //editor button cancel changes click event
          this.controls.container.on("click",'#' + def.C_BUTTONCANCEL,function(){
              that.restoreTile();
          });


          //"Add new tile" button click event
          this.controls.buttonaddnew.click(function(){
              var currentcount = transcriptionset.fetchCount();
              var newdataid=currentcount;
              if(currentcount >0){
                  var lastitem = transcriptionset.fetchItem(currentcount-1);
              }

              transcriptionset.addItem(newdataid,'');

              var onend = function(newtile){
                  //append new tile
                  that.controls.container.append(newtile);
                  //its likely you want to edit it too, so we click it
                  $('.poodllconvedit_itemcontainer[data-id="' + newdataid + '"] .poodllconvedit_tt').trigger('click');
              };
              var newtile = that.fetchNewTextTileContainer(newdataid,'',onend);

          });


      },


      //each transcription item has a "text tile" with transcript text that we display
      //when clicked we swap it out for the editor
      //this takes all the transcription json and creates one tiles on page for each transcript part
      initTiles: function(){
          var container = this.controls.container;
          var that = this;
          var setcount = transcriptionset.fetchCount();

          //the first render of template takes time and puts the first tile rendered after subsequent tiles,
          // a better way might be force the order but for now we force an empty first render, and set reallyInitTiles to run after that
          var reallyInitTiles = function() {
              if (setcount > 0) {
                  for (var setindex = 0; setindex < setcount; setindex++) {
                      var item = transcriptionset.fetchItem(setindex);
                      var onend = function (newtile) {
                          container.append(newtile);
                      };
                      var newtile = that.fetchNewTextTileContainer(setindex, item.part, onend);
                  }
                  ;//end of for loop
              }//end of if setcount
          };
          this.fetchNewTextTile(0,'',reallyInitTiles);
      },


      //Replace text tile we are editing with the editor, fill with data and display it
      shiftEditor: function(newindex,newitemcontainer){

          //hide editor
          this.controls.editor.hide();

          //newitem
          var newitem =transcriptionset.fetchItem(newindex);

          var part = newitem.part;
          $(this.controls.edpart).val(part);

          //remove old text tile and show editor in its place
          newitemcontainer.empty();
          newitemcontainer.append(this.controls.editor);
          this.controls.editor.show();
          this.editoropen=true;

          $(this.controls.number).text(newindex + 1);

      },

      //Merge a template text tile,  with the time and transcription text data
      fetchNewTextTile: function(dataid, part, onend){
          var tdata=[];
          tdata['imgpath'] = M.cfg.wwwroot + '/mod/solo/pix/e/';
          tdata['dataid'] = dataid+1;
          tdata['part'] = part;

          templates.render('mod_solo/convtile',tdata).then(
              function(html,js){
                    onend(html);
              }
          );
      },

      //Merge a template text tile,  with the time and transcription text data
      fetchNewTextTileContainer: function(dataid, part, onend){
          var tdata=[];
          tdata['imgpath'] = M.cfg.wwwroot + '/mod/solo/pix/e/';
          tdata['outerdataid'] = dataid;
          tdata['dataid'] = dataid+1;
          tdata['part'] = part;

          templates.render('mod_solo/convtilecontainer',tdata).then(
              function(html,js){
                    onend(html);
              }
          );
      },

      clearTiles: function(){
          this.controls.container.empty();
      },

      resetData: function(transcriptiondata){
          this.hideEditor();
          this.clearTiles();
          transcriptionset.init(transcriptiondata);
          this.initTiles();
          this.doSave();
      },

      syncFrom: function(index){
          var setcount = transcriptionset.fetchCount();
          var that=this;
          for(var setindex=index; setindex < setcount;setindex++){
              var item =transcriptionset.fetchItem(setindex);
              var container = $('.poodllconvedit_itemcontainer').filter(function() {
                  return parseInt($(this).attr("data-id")) == setindex;
              });
              if(container.length > 0){
                  this.updateTextTile(container,item);
              }else{
                  var onend = function(newtile){that.controls.container.append(newtile);};
                  var newtile = this.fetchNewTextTileContainer(setindex,item.part,onend);
              }
          }
          //remove any elements greater than the last data-id
          $('.poodllconvedit_itemcontainer').filter(function() {
              return parseInt($(this).attr("data-id")) >= setcount;
          }).remove();
      },
      syncAt: function(index){
          //do something

      },
      updateTextTile: function(container,item){
          $(container).find('.poodllconvedit_tt_part').text(item.part);
          return;
      },


      fetchTranscriptionData: function(){
          return transcriptionset.fetchTranscriptionData();
      },

      //overwrite this in your calling class
      doSave: function(){
          //do something
      }
  }
});
