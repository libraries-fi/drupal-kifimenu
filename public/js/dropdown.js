/**
 * Javascript features for accessible drop-down menu.
 */
(function($) {
  "use strict";

  var CLASS_EXPANDED = "expanded";

  var Key = {
    "Enter": 13,
    "Esc": 27,
    "Space": 32,
    "End": 35,
    "Home": 36,
    "Left": 37,
    "Up": 38,
    "Right": 39,
    "Down": 40,
  };

  $.fn.kifiMenu = function() {
    var root = this;
    var menus = this.find(".menu");
    var items = this.find(".menu-item--tree");
    var links = this.find("a[href]");

    function expand_tree(item) {
      collapse_tree(root.find("." + CLASS_EXPANDED));
      item.addClass(CLASS_EXPANDED);
      item.children("button")
        .attr("aria-expanded", "true")
        .attr("aria-label", Drupal.t("Close"));
    }

    function collapse_tree(item) {
      item.removeClass(CLASS_EXPANDED);
      item.children("button")
        .attr("aria-expanded", "false")
        .attr("aria-label", Drupal.t("Open"));
    }

    function onToggle(event) {
      var button = $(event.target);
      var menu = button.siblings(".menu").first().closest(".menu-item");

      if (button.attr("aria-expanded") == "true") {
        collapse_tree(menu);
      } else {
        expand_tree(menu);
      }
    }

    function onMenuButtonKey(event) {
      var menu = $(event.target).closest(".menu-item");

      switch (event.keyCode) {
        case Key.Down:
          expand_tree(menu);
          menu.find(".menu > .menu-item:first > a").focus();
          break;

        case Key.Up:
          expand_tree(menu);
          menu.find(".menu > .menu-item:last > a").focus();
          break;

        default:
          return;
      }

      event.preventDefault();
    }

    function onMenuKey(event) {
      var items = $(event.currentTarget).find("> .menu-item > a");
      var item = event.target;

      switch (event.keyCode) {
        case Key.Down:
          var i = (items.index(item) + 1) % items.length;
          $(items[i]).focus();
          break;

        case Key.Up:
          var current = items.index(item);
          var next = (current == 0 ? items.length : current) - 1;
          $(items[next]).focus();
          break;

        case Key.Home:
          $(items[0]).focus();
          break;

        case Key.End:
          $(items[items.length - 1]).focus();
          break;

        case Key.Esc:
          var item = $(event.target).closest(".menu").closest(".menu-item");
          var button = item.children("button");
          collapse_tree(item);
          button.focus();
          break;

        default:
          return;
      }

      event.preventDefault();
    }

    menus.attr("role", "menu");
    links.attr("role", "menuitem");

    // Clear implied role "listitem".
    items.attr("role", "none");

    // Handle arrow keys in submenus.
    menus.slice(1).on("keydown", onMenuKey);

    items.each(function(i, _item) {
      var item = $(_item);
      var link = item.children("a");

      var button = $("<button/>")
        .addClass("menu-toggle")
        .attr("type", "button")
        .attr("aria-haspopup", "true")
        .attr("aria-expanded", item.hasClass("expanded") ? "true" : "false")
        .attr("aria-label", item.hasClass("expanded") ? Drupal.t("Close") : Drupal.t("Open"))
        .on("click", onToggle)
        .on("keydown", onMenuButtonKey);

      link.after(button);
      item.addClass("has-submenu");
    });
  };

  $(".kifimenu-dropdown").kifiMenu();
}(jQuery));
