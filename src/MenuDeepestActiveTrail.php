<?php

namespace Drupal\kifimenu;

use Drupal\Core\Menu\MenuActiveTrail;

/**
 * Fetches the DEEPEST matching trail.
 *
 * This is because in our menus some top-level items are just placeholders for a sub-link below them,
 * but for side menus we want to track that child menu item.
 */
class MenuDeepestActiveTrail extends MenuActiveTrail {
  public function getActiveLink($menu_name = NULL) {
    $found = NULL;
    $route_name = $this->routeMatch->getRouteName();

    if ($route_name) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      $links = $this->menuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name);

      if ($links) {
        $found = end($links);
      }
    }

    return $found;
  }
}
