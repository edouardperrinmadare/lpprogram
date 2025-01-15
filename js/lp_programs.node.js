/**
 * @file
 * Javascript for the LP Programs poi map.
 */

(function (Drupal, $) {

  'use strict';

  /**
   * The LP Programs search behavior.
   */
  Drupal.behaviors.lp_programs_node = {
    attach: function (context, settings) {
      $('.program-neighborhood__map .geofield-google-map').once('lp_program_gmap').each(function () {
          let mapId = this.id;

          Drupal.geoFieldMapFormatter.addCallback(function () {
            let interval = setInterval(whenGeoFieldMapReady, 250);

            function whenGeoFieldMapReady() {
              if (typeof Drupal.geoFieldMapFormatter.map_data[mapId].map !== 'object') {
                return;
              }
              clearInterval(interval); // Stop the interval.

              // Init map poi.
              Drupal.lp_programs_node_poi_map.init(mapId);
            }
          });
        }
      );

      $(window).once().on({
        'dialog:beforecreate': function(dialog, $element, settings) {
          $('.mobile-menu').hide();
        },
        'dialog:aftercreate': function(dialog, $element, settings) {
          document.body.style.top = '-' + window.scrollY + 'px';
          document.body.style.position = 'fixed';
          document.body.style.width = '100%';
        },
        'dialog:afterclose': function(dialog, $element, settings) {
          console.log('modal is closed!');
          $('.mobile-menu').show();
          const scrollY = document.body.style.top;
          document.body.style.position = '';
          document.body.style.top = '';
          document.body.style.width = '';
          window.scrollTo(0, parseInt(scrollY || '0') * -1);
        }
      });
    }
  }

  Drupal.lp_programs_node_poi_map = {

    map: {},

    mainMarker: {},

    types: '',

    infowindow: {},

    markers: [],

    iconBasePath: '/themes/custom/lp_promotion/assets/images/pictos/map/',

    poi_list: [],

    // Only for debug duplicate poi.
    poi_list_name: [],

    // Only for debug duplicate poi.
    duplicate_poi_list_name: [],

    /**
     * Init map poi.
     */
    init: function (mapId) {
      let _this = this;
      this.map = Drupal.geoFieldMapFormatter.map_data[mapId].map;
      this.mapMarkers = Drupal.geoFieldMapFormatter.map_data[mapId].markers;
      this.mapMarkers = Drupal.geoFieldMapFormatter.map_data[mapId].markers;

      // Get main marker = get the first element.
      this.mainMarker = this.mapMarkers[Object.keys(this.mapMarkers)[0]];

      this.infowindow = new google.maps.InfoWindow();

      // Close infowinfow when click on the map.
      this.map.addListener("click", () => {
        _this.infowindow.close();
      });

      // Disabled: hiding default google map poi in field display configuration.
      // this.hideDefaultPoi();

      this.overrideMainMarker();

      this.getPoi();

      $.each(this.poi_list, function () {
        _this.addMarker(this);
      });

      this.addFilterLinkEvent();
    },

    /**
     * Override main marker :
     * - Update icon placement.
     * - Update infowindow placement.
     */
    overrideMainMarker: function () {
      let _this = this;

      // Set proper icon placement.
      this.mainMarker.setIcon({
        url: this.iconBasePath + 'lp.svg',
        size: new google.maps.Size(80, 80),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(40, 40),
        // anchorPoint: new google.maps.Point(5, 10),
      });

      // Set zIndex to keep the main marker above.
      this.mainMarker.setZIndex(100);

      // Clear default click event.
      google.maps.event.clearInstanceListeners(this.mainMarker, 'click');

      // Set infowindow.
      google.maps.event.addListener(this.mainMarker, 'click', function () {
        _this.infowindow.setContent('<h3>' +
          _this.mainMarker.title +
          '</h3>');
        _this.infowindow.setOptions({pixelOffset: new google.maps.Size(0, 6)});
        _this.infowindow.open(_this.map, this);
      });
    },

    /**
     * Get poi list.
     */
    getPoi: function () {
      let _this = this;

      $.each(drupalSettings.lp_program.poi, function (index, group_poi) {
        let type = group_poi.type;

        $.each(JSON.parse(group_poi.data), function (index, poi_list) {

          // Dig to reach poi data and flat data on another array.
          $.each(poi_list, function (index, poi) {
            if (!_this.isDuplicatePoi(poi.place_id)) {
              // if (!_this.isDuplicatePoi(poi.name)) {
              _this.poi_list.push({
                type: type,
                data: poi
              });
            }
          });
        });
      });
    },

    /**
     * Add filter link event.
     */
    addFilterLinkEvent: function () {
      let _this = this;

      let $links = $('.program-neighborhood__map nav .nav-link');
      $links.once('filterMapPoi').on('click', function (e) {
        e.preventDefault();
        let $this = $(this);

        $links.removeClass('active');
        $this.addClass('active');

        let featureType = $this.data('poi');

        // Remove all markers.
        $.each(_this.markers, function (index, marker) {
          marker.setMap(null);
        });

        if (featureType == 'all') {
          $.each(_this.markers, function (index, marker) {
            marker.setMap(_this.map);
          });
        }
        else {
          $.each(_this.markers, function (index, marker) {
            if (featureType === marker.type) {
              marker.setMap(_this.map);
            }
          });
        }
      });
    },

    /**
     * Filter duplicate poi.
     */
    isDuplicatePoi: function (place_id) {
      let _this = this;

      if ($.inArray(place_id, _this.poi_list_name) !== -1) {
        _this.duplicate_poi_list_name.push(place_id);
        return true;
      }
      else {
        _this.poi_list_name.push(place_id);
        return false;
      }
    },

    /**
     * Add marker on map.
     */
    addMarker: function (poi) {
      let _this = this;
      let distance = poi.data.direction.routes[0].legs[0].distance.text;
      let duration = poi.data.direction.routes[0].legs[0].duration.text;

      // Add marker.
      let marker_options = {
        map: this.map,
        position: poi.data.geometry.location,
        icon: {
          url: this.iconBasePath + poi.type + '.svg',
          size: new google.maps.Size(70, 70),
          origin: new google.maps.Point(0, 0),
          anchor: new google.maps.Point(35, 35)
        },
        type: poi.type,
        distance: distance,
        duration: duration
      }
      let marker = new google.maps.Marker(marker_options);

      // Save marker to filter it later.
      this.markers.push(marker);

      // Set infowindow.
      google.maps.event.addListener(marker, 'click', function () {
        _this.infowindow.setContent('<h3>' +
          poi.data.name +
          '</h3>' +
          '<div>' + distance + '</div>' +
          '<div style="display:none;">' + duration + ' à pied</div>'
        );
        _this.infowindow.setOptions({pixelOffset: new google.maps.Size(0, 5)});

        _this.infowindow.open(_this.map, this);
      });
    },

    /**
     * Hide default map poi.
     * DISABLED.
     */
    hideDefaultPoi: function () {
      const styles = {
        default: [],
        hide: [
          {
            featureType: "poi",
            stylers: [{visibility: "off"}],
          },
          {
            featureType: "transit",
            stylers: [{visibility: "off"}],
          },
        ],
      };

      this.map.setOptions({styles: styles['hide']});
    },

  }

})
(Drupal, jQuery);
