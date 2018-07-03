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
      $parents = [];

      foreach ($links as $link) {
        $id = $link->getPluginId();
        $parents[$id] = $this->menuLinkManager->getParentIds($id);
      }

      // Sort links so that deepest is first.
      usort($links, function($a, $b) use($parents) {
        return count($parents[$b->getPluginId()]) - count($parents[$a->getPluginId()]);
      });

      if ($links) {
        $found = reset($links);
      }
    }

    return $found;
  }

  protected function getCid() {
    $cid = parent::getCid();
    $cid = 'deepest-' . $cid;
    return $cid;
  }
}
