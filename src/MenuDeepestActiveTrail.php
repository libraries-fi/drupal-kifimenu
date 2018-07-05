<?php

namespace Drupal\kifimenu;

use Drupal\Core\Menu\MenuActiveTrail;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

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
      $found = $this->resolveRoute($route_name, $menu_name);
    }

    if (!$found) {
      // Try to resolve to a parent link with nodes etc.

      $current_path = \Drupal::service('request_stack')->getCurrentRequest()->getPathInfo();
      $last_slash = strrpos($current_path, '/');
      $parent_path = substr($current_path, 0, $last_slash);
      $url = \Drupal::service('path.validator')->getUrlIfValid($parent_path);

      if ($url) {
        $found = $this->resolveRoute($url->getRouteName(), $menu_name, $url);
      }
    }

    return $found;
  }

  protected function resolveRoute($route_name, $menu_name, Url $override = NULL) {
    $found = NULL;
    $route_parameters = $override ? $override->getRouteParameters() : $this->routeMatch->getRawParameters()->all();
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

    return $found;
  }

  protected function getCid() {
    $cid = parent::getCid();
    $cid = 'deepest-' . $cid;
    return $cid;
  }
}
