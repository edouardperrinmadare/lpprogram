/**
 * @file
 * Javascript for the LP Programs search map.
 */

(function (Drupal, $) {

  'use strict';

  /**
   * The LP Programs search behavior.
   */


  
  Drupal.behaviors.lp_programs_search = {
    attach: function (context, settings) {
      $.each(settings.views.ajaxViews, function (domId, viewSettings) {
          // Select all label elements with class 'option' within the container 'form-checkboxes bef-checkboxes'
          const labels = document.querySelectorAll('[data-drupal-selector="edit-field-program-types-target-id"] .form-checkboxes.bef-checkboxes .option');

          // Iterate over each label
          labels.forEach(label => {
            // Replace 'T' with an empty string in the label's text
            label.textContent = label.textContent.replace(/T/g, '');
          });
        // Check if we have facet for this view.
        if (viewSettings.view_name === 'programmes_madare' && viewSettings.view_display_id === 'attachment_1') {
          let $view = $('.js-view-dom-id-' + viewSettings.view_dom_id);
          if ($view.length) {
            // Only force update facets if view args is not empty.
            if (viewSettings.view_args !== undefined && viewSettings.view_args !== '' && viewSettings.view_args !== 'all') {
              // Save view settings.
              Drupal.lp_programs_search.viewSettings = viewSettings;


            }
            else {
              if (Drupal.lp_programs_search.prevViewArgs) {
                // Only force update facets if view args become empty.
                Drupal.lp_programs_search.viewSettings = viewSettings;
              }

              // Reset view args.
              Drupal.lp_programs_search.prevViewArgs = false;
            }
          }
        }
      });

      $('#lp-programs-view-map .geofield-google-map').once('lp_gmap').each(function () {
        let mapId = this.id;
        if ($('.filter-hide .form-item-place').length == 0) {
          $('#filters .form-item-place').appendTo('.filter-hide');
          $('.form-item-place input').attr('form', 'views-exposed-form-programmes-madare-page-1');
        } else {
          setTimeout(function() {
            $('#filters .form-item-place').remove();
        }, 300);
        }
      // Assuming you've already moved your input outside the form with your existing code

        // Select the input and the form
        var input = $('.form-item-sort-by select'); // adjust this selector based on your input's actual structure

        // Add a change event listener to the input
        input.on('change', function() {
            // You can directly submit the form
            $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"] .form-submit').trigger('click');
            
            // Or if you have specific logic to handle the submit, call that here
            // customFormSubmitFunction();
        });

        // Your existing code to move the input
        if ($('.filter-hide .form-item-sort-by').length == 0) {
            $('#filters .form-item-sort-by').appendTo('.filter-hide');
            $('.form-item-sort-by select').attr('form', 'views-exposed-form-programmes-madare-page-1');
        } else {
          setTimeout(function() {
            $('#filters .form-item-sort-by').remove();
        }, 300);

        }




        Drupal.geoFieldMapFormatter.addCallback(function () {
          let interval = setInterval(whenGeoFieldMapReady, 200);

          function whenGeoFieldMapReady() {
            if (typeof Drupal.geoFieldMapFormatter.map_data[mapId].map !== 'object') {
              return;
            }
            clearInterval(interval); // Stop the interval.
            updateGeoFieldMap();
          }

          function updateGeoFieldMap() {
            const mapData = Drupal.geoFieldMapFormatter.map_data[mapId];
            const map = mapData.map;
            const markers = mapData.markers;

            // Set the map data.
            Drupal.lp_programs_search.mapData = mapData;

            // Set the map setting.
            Drupal.lp_programs_search.map = map;

            // Set the markers setting.
            Drupal.lp_programs_search.markers = markers;

            // Init map search.
            Drupal.lp_programs_search.initMapSearch();
          }
        });

      });
    }
  };

  Drupal.lp_programs_search = {
    mapData: {},

    map: {},

    markers: {},

    prevMarker: {},

    viewSettings: {},

    prevViewArgs: false,

    /**
     * Handle when map empty result.
     */
    initMapSearch: function () {
      // Empty map result.
      if ($.isEmptyObject(this.markers)) {
        this.noResult();
      }

      // Only for mobile & tablet.
      const mediaQuery = window.matchMedia('(max-width: 1000px)')
      if (mediaQuery.matches) {
        this.initSwitchDisplay();
      }

      this.setResultCount();
      this.setFiltersCount();
      this.addPlacesAutocomplete();
      this.handleResetPlaceAutocomplete();
      this.initPriceFilter();
      this.initInRunFilter();

      // Map has results.
      if (!$.isEmptyObject(this.markers)) {
        this.addMarkerEvents();
        this.initInfowindow();
      }
    },

    /**
     * Handle when map empty result.
     */
    noResult: function () {
      let params = this.getSearchFormParameters();
      console.log('pas de resultats');
      // Center on France by default.
      let positionArray = ['46.6338185835549', '2.436256700543203'];
      let zoom = 6;

      // Center on user localtion.
      if (params['field_geol[source_configuration][origin_address]'] != '') {
        positionArray = params['field_geol[source_configuration][origin_address]'].split(',');
        zoom = 10;
      }

      let position = {
        lat: parseFloat(positionArray[0]),
        lng: parseFloat(positionArray[1])
      };

      let options = {
        zoom: zoom,
        center: position
      };

      // Override map position from Geomap module that center
      // automatically map on default position when no result.
      this.map.setOptions(options);
    },

    /**
     * Get exposed form search parameters.
     */
    getSearchFormParameters: function () {
      var $exposed_form = $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"]');
      var params = [];

      $.each($exposed_form.serializeArray(), function (index, value) {
        params[this.name] = this.value;
      });

      return params;
    },

    /**
     * Init infowindow.
     */
    initInfowindow: function () {
      let self = this;

      // Set infowindow closed property.
      self.map.infowindow.set('closed', true);

      // Close infowinfow when click on the map.
      self.map.addListener("click", () => {
        self.map.infowindow.close();
        self.resetInfowindow();
      });

      // Reset marker icon when infowindow closed.
      self.map.infowindow.addListener('closeclick', () => {
        self.resetInfowindow();
      });
    },

    /**
     * Set result count to modal button.
     */
    setResultCount: function () {
      let filtersCount = this.getFiltersActiveCount();

      if (!filtersCount) {
        $('.js-btn-see-results')
          .prop('disabled', false)
          .html('Voir les résulats');
        return;
      }

      if ($('.view--programmes-madare--attachment-1 header').length === 0 || $('.view--programmes-madare--attachment-1').text().trim() === '') {
        $('.js-btn-see-results')
            .prop('disabled', true)
            .text('Aucun résultat');
    } else {
        $('.view--programmes-madare--attachment-1 header').once('result-count').each(function () {
            let resultCount = parseInt($(this).text());
            console.log('hohoho');
            if (Number.isNaN(resultCount)) {
              $('.js-btn-see-results')
                .prop('disabled', true)
                .text('Aucun résultat');
            } else {
              $('.js-btn-see-results')
                .prop('disabled', false)
                .text('Voir les ' + resultCount + ' résultats');
            }
        });
    }
    },

    /**
     * Get result count.
     */
    getFiltersActiveCount: function () {
      let filtersCount = 0;

      let facetActiveLinks = $('.facets-widget-links a.is-active');
      if (facetActiveLinks.length) {
        filtersCount += facetActiveLinks.length;
      }

      if ($('input[name="prix_min').val()) {
        filtersCount++;
      }

      if ($('input[name="prix_max').val()) {
        filtersCount++;
      }

      if ($('#filters input:checked').length) {
        filtersCount += $('#filters input:checked').length;
      }

      if (!$('[data-drupal-selector="edit-field-address-administrative-area"]').val() == "All") {
        filtersCount++;
      }

      return filtersCount;
    },

    /**
     * Set filter active count to Filters button.
     */
    setFiltersCount: function () {
      let filtersCount = this.getFiltersActiveCount();

      $('.view--programmes-madare--attachment-1 header').once('filters-count').each(function () {
        if (!filtersCount) {
          $('.filters-count').text('');
        }
        else {
          $('.filters-count').text(filtersCount);
        }
      });
    },

    /**
     * Add Places autocomplete.
     */
    addPlacesAutocomplete: function () {
      let _this = this;

      const $input = $('#place-autocomplete-field');

      // Check if field is empty and try to get value in drupalSettings.
      if (!$input.val()) {
        if (drupalSettings.lp_programs_search !== undefined && drupalSettings.lp_programs_search.place !== undefined) {
          $input.val(drupalSettings.lp_programs_search.place);
        }
      }

      const options = {
        componentRestrictions: {country: "fr"},
        types: ['(regions)'],
        fields: ["geometry"]
      };

      const autocomplete = new google.maps.places.Autocomplete($input.get(0), options);
      autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();

        if (place.length === 0 || !$input.val()) {
          $('#place-autocomplete-field').trigger('search');
          return;
        }

        if (!place.hasOwnProperty('geometry')) {
          return;
        }

        // Remove country name from input.
        let city = $input.val().replace(', France', '');
        $input.val(city)

        const location = place.geometry.location;
        const position = location.lat() + ',' + location.lng();

        $('.modal-body [data-drupal-selector="edit-field-geol-source-configuration-origin-address"]').val(position);
        $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"] .form-submit').trigger('click');
      });
    },

    /**
     * Reset place autocomplete.
     */
    handleResetPlaceAutocomplete: function () {
      let delay = 1500;
      let time = 0;
      let $fieldPlaceAutocomplete = $('#place-autocomplete-field');

      // Reset when change when the field value with backspace.
      /*
      $fieldPlaceAutocomplete.on('input', function () {
        let $this = $(this);

        // Reset the timer.
        clearTimeout(time);

        if (!$this.val()) {
          time = setTimeout(function () {
            $fieldPlaceAutocomplete.trigger('search');
          }, delay);
        }
      });
      */

      // Reset when clear the field value with the small cross button.
      $fieldPlaceAutocomplete.on('search', function () {
        const $fieldGeo = $('[data-drupal-selector="edit-field-geol-value"]');
        if ($fieldGeo.val()) {
          $('[data-drupal-selector="edit-field-geol-source-configuration-origin-address"]').val('');
          $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"] .form-submit').trigger('click');
        }
      });

      // Disable enter to select.
      // Select first option on Enter.
      $fieldPlaceAutocomplete.on('keydown', function (e) {
        if (e.which == 13 && $('.pac-container:visible').length) {
          return false;
        }
      });
      this.enableEnterKey($fieldPlaceAutocomplete[0]);
    },

    /**
     * Select first autocomplete Google Place option on enter.
     */
    enableEnterKey: function (input) {
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
    },

    /**
     * Prevent Bootstrap Collapse from hiding element itself.
     */
    initSwitchDisplay: function () {
      let $buttons = $('[data-group="list-map-switch"]');

      $buttons.once('list-map-switch').on('click', function () {
        let $this = $(this);
        let $target = $($this.data('target'));

        // Hide other target.
        $($buttons).each(function () {
          let $otherThis = $(this);
          $otherThis.removeClass('on');

          let $otherTarget = $($otherThis.data('target'));
          $otherTarget.hide();
        });

        // Show current target.
        $this.addClass('on');
        $target.show();
      });

      $buttons.filter('.on').trigger('click');
    },

    /**
     * Add map markers events.
     * - Onclick marker.
     * - Mouseover program card.
     */
    addMarkerEvents: function () {
      let self = this;
      // Add list program mouseover.
      $('#lp-programs-view-cardslist .program--card').once('program-list-mouseover').each(function () {
        let $this = $(this);
        let nid = $this.data('nid');
        let thisMarker = self.markers[nid];
        let thisMarkerId = $this.data('marker-icon');

        $this
          .on('mouseenter', function () {
            let icon = thisMarker.icon.replace(thisMarkerId, thisMarkerId + '-on');
            thisMarker.setIcon(icon);
          })
          .on('mouseleave', function () {
            let icon = thisMarker.icon.replace(thisMarkerId + '-on', thisMarkerId);
            thisMarker.setIcon(icon);
          });
      });

      // Add marker click event.
      $.each(self.markers, function (nid, marker) {
        let icon = marker.icon;

        if (icon === undefined) {
          return true; // continue to next marker.
        }

        // Filter click event that spiderfy marker.
        self.mapData.oms.addListener('click', function (markerArg, eventArg) {
          if (marker === markerArg) {
            // Abort if same marker clicked.
            if (self.prevMarker.geojsonProperties !== undefined) {
              if (marker.geojsonProperties.entity_id === self.prevMarker.geojsonProperties.entity_id) {
                return;
              }
            }

            // tid is used in icon name.
            // Rule is poi-[tid].svg and for active poi-[tid]-on.svg
            // Strip casual html (in debug twig mode).

            let tid = marker.geojsonProperties.data.entity_id.replace(/(<([^>]+)>)/gi, "");

            let icon = marker.icon.replace(tid, tid + '-on');
            marker.setIcon(icon);

            // Change icon back for previous clicked marker.
            if (self.prevMarker.icon !== undefined) {
              let previousTid = self.prevMarker.geojsonProperties.data.entity_id.replace(/(<([^>]+)>)/gi, "");
              let previousIcon = self.prevMarker.icon.replace(previousTid + '-on', previousTid);
              self.prevMarker.setIcon(previousIcon);
            }

            self.prevMarker = marker;
          }
        });
      });
    },

    /**
     * Set infowindow status to closed.
     * Set marker icon.
     */
    resetInfowindow: function () {
      this.map.infowindow.set('closed', true);
      let icon = this.prevMarker.icon.replace('-on', '');
      this.prevMarker.setIcon(icon);
      this.prevMarker = {};
    },

    initInRunFilter: function () {
      // Variable declaration
      let $forsale_field = $('input[id="commercialisation-du-projet-forsale"]');
      const newLocal = 'initProject';
      const $initForSaleProject = (sessionStorage.getItem(newLocal) == null);
      let $forsaleOn = window.location.search.indexOf("commercialisation_du_projet%3Aforsale");
      let $previewOn = window.location.search.indexOf("commercialisation_du_projet%3Apreview");
    
      // Init process - set empty storage 
      if ($forsaleOn == -1 && $previewOn == -1 && $initForSaleProject) {
        sessionStorage.setItem(newLocal, 'intForsaleProject');
      }
    
      // Init process - click forsale and preview fields
      if ($initForSaleProject) {
        if (sessionStorage.getItem(newLocal) == 'intForsaleProject') {
          let $param_prefix;
          if (window.location.href.indexOf("?") == -1) {
            $param_prefix = '?';
          } else {
            $param_prefix = '&';
          }
    
          $(document).ready(function () {
            let newUrl = window.location.href + $param_prefix + 'commercialisation_du_projet%5Bforsale%5D=forsale';
            newUrl += '&commercialisation_du_projet%5Bpreview%5D=preview';
            window.location.href = newUrl;
          });
        }
      }
    
      // Reset storage on change page
      $('a').click(function() {
        let $hrefToGo = $(this).attr("href").indexOf("?");
        if ($hrefToGo == -1 && !$initForSaleProject) {
          sessionStorage.removeItem(newLocal);
        }
      });
    },

    /**
     * Copy fake price min/max field to exposed form field and submit it.
     */
    initPriceFilter: function () {
      let $fake_field_prices = $('input[name="fake_prix_min"], input[name="fake_prix_max"]');
      let time = 0;

      // Copy filed price value.
      $fake_field_prices.each(function () {
        let $this = $(this);
        let $field_price = $('input[name="' + $this.data('name-target') + '"]');

        const itemSetPrix = (sessionStorage.getItem($this.data('name-target')) !== null);
        if(itemSetPrix) {
          let $store_price = sessionStorage.getItem($this.data('name-target'));
          $field_price.val($store_price);
          $this.val($field_price.val());
          sessionStorage.removeItem($this.data('name-target'));

          time = setTimeout(function () {
            $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"] .form-submit').trigger('click');
          }, 300);

        } else {
          //console.log('$store_price localStore is null');
          
          $this.val($field_price.val());
        }
      });

      // On change paste keyup.
      let delay = 3000;
      $fake_field_prices.on('input', function () {
        let $this = $(this);

        // Reset the timer.
        clearTimeout(time);

        let price_value = parseInt($this.val());

        let $field_price = $('input[name="' + $this.data('name-target') + '"]');

        if (isNaN(price_value)) {
          $this.val('');
          if (!$field_price.val()) {
            return;
          }
          $field_price.val('');
        }
        else {
          $field_price.val(price_value);
        }

        time = setTimeout(function () {
          $('.modal-body [data-drupal-selector="views-exposed-form-programmes-madare-page-1"] .form-submit').trigger('click');
        }, delay);
      });

    },

 

  }

})(Drupal, jQuery);
