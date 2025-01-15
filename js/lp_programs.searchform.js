(function (Drupal, $) {
  'use strict';

  /**
   * Add Places autocomplete.
   */
  function addPlacesAutocomplete() {
    const $input = $('#edit-location');
    const options = {
      componentRestrictions: {country: "fr"},
      types: ['(regions)'],
      fields: ["geometry"]
    };

    const autocomplete = new google.maps.places.Autocomplete($input.get(0), options);
    autocomplete.addListener("place_changed", () => {
      const place = autocomplete.getPlace();

      if (!place.hasOwnProperty('geometry')) {
        return;
      }

      // Remove country name from input.
      let city = $input.val().replace(', France', '');
      $input.val(city);

      const location = place.geometry.location;
      const position = location.lat() + ',' + location.lng();

      // Save data to sessionStorage.
      let mapPosition = {lat: location.lat(), lng: location.lng()};

      $('[data-drupal-selector="edit-position"]').val(position);
    });

    // Disable enter to select.
    // Select first option on Enter.
    $input.on('keydown', function (e) {
      if (e.which == 13 && $('.pac-container:visible').length) {
        // alert('Veuillez choisir une localité dans la liste');
        return false;
      }
    });
    enableEnterKey($input[0]);
  }

  /**
   * Select first autocomplete Google Place option on enter.
   */
  function enableEnterKey(input) {
    /* Store original event listener */
    const _addEventListener = input.addEventListener

    const addEventListenerWrapper = (type, listener) => {
      if (type === 'keydown') {
        /* Store existing listener function */
        const _listener = listener
        listener = (event) => {
          /* Simulate a 'down arrow' keypress if no address has been selected */
          const suggestionSelected = document.getElementsByClassName('pac-item-selected').length
          if (event.key === 'Enter' && !suggestionSelected) {
            const e = new KeyboardEvent('keydown', {
              key: 'ArrowDown',
              code: 'ArrowDown',
              keyCode: 40,
            })
            _listener.apply(input, [e])
          }
          _listener.apply(input, [event])
        }
      }
      _addEventListener.apply(input, [type, listener])
    }

    input.addEventListener = addEventListenerWrapper
  }

  /**
   * On ready Init function.
   */
  $(function() {
    addPlacesAutocomplete();
  });

})(Drupal, jQuery);
