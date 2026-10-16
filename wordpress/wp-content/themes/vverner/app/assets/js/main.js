jQuery(function ($) {
   const ajaxUrl = vvData.ajaxUrl;
   const ibgeUrl = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';
   $.getJSON( ibgeUrl + "?orderBy=nome", function( estados ) {
      let options = '';
      for(const estado of estados){
          options += '<option value="'+ estado.nome +'" data-id="'+ estado.id +'">'+ estado.nome +'</option>';
      }
      $('.populateStates').append(options)
  });

  $('.populateStates').change(function(){
      const id = $(this).find('option:selected').data('id');
      const $target = $(this).parents('.col').next().find('.populateCities');
      $.getJSON( ibgeUrl + "/"+ id +"/municipios", function( cidades ) {
          let options = '<option value="">---</option>';
          for(const cidade of cidades){
              options += '<option value="'+ cidade.nome +'">'+ cidade.nome +'</option>';
          }
          $target.empty().append(options);
      });
  });
});