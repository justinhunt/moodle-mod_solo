define(["jquery"], function($) {

    //transcription set is the data layer for the transcriptions. its an array of objects,
    // with methods to access and manipulate items and the array.

  return {
       stitles: [],

      init: function(transcriptiondata){
            this.stitles = transcriptiondata;
      },

      fetchTranscriptionData: function(){
          return this.stitles;
      },

      fetchCount: function(){
          return this.stitles.length;
      },
      addItem: function(index,part){
          var item = this.makeItem(part);
          this.stitles.push(item);
      },
      insertItem: function(index,part){
          var item = this.makeItem(part);
          this.stitles.splice(index, 0, item);
      },
      removeItem: function(index){
          this.stitles.splice(index, 1);
      },
      fetchItem: function(index){
          return this.stitles[index];
      },

      updateItem: function(index,part){

          this.stitles[index].part = part;
      },
      movedown: function(index){
        //basic error check. Should not get here in this case anyway
        if(!this.canmovedown(index)){return false;}
         this.moveItem(index, index+1);
        return true;

      },
      moveup: function(index){
          //basic error check. Should not get here in this case anyway
          if(!this.canmoveup(index)){return false;}
          this.moveItem(index, index-1);
          return true;
      },
      moveItem: function(from, to) {
        // remove `from` item and store it
        var f = this.stitles.splice(from, 1)[0];
        // insert stored item into position `to`
          this.stitles.splice(to, 0, f);
        },

      canmoveup: function(index){
          if(index==0 || this.fetchCount()<2){
              return false;
          }else{
              return true;
          }
      },
      canmovedown: function(index){
          if(index>=this.fetchCount()-1 || this.fetchCount()<2){
              return false;
          }else{
              return true;
          }
      },
      makeItem: function(part){
         return { part: part};
      },

      //UNUSED ... buyt worth keeping
      split: function(index){
          var originalitem = this.fetchItem(index);
          var originalduration = originalitem.end - originalitem.start;

          //if its less than a third of a second our calcs might error(or someone is trying subliminal advertising)
          //lets just stop right here
          if(originalduration < 300){return false;}

          var firststart = originalitem.start;
          var firstend =  originalitem.start + (originalduration / 2) - 100;
          var secondstart = firstend + 200;
          var secondend = originalitem.end;


          this.insertItem(index,firststart,firstend,originalitem.part);
          this.updateItem(index+1,secondstart,secondend,originalitem.part);
          return true;

      },
      //UNUSED  .. but worth keeping
      mergeUp: function(index){
          //basic error check. Should not get here in this case anyway
          if(!this.canMergeUp(index)){return false;}

          var upperitem = this.fetchItem(index);
          var loweritem = this.fetchItem(index-1);
          this.updateItem(index-1,loweritem.start,upperitem.end, loweritem.part + " " + upperitem.part);
          this.removeItem(index);
          return true;
      },
      canMergeUp: function(index){
          if(index==0 || this.fetchCount()<2){
              return false;
          }else{
              return true;
          }
      },

    }
});
