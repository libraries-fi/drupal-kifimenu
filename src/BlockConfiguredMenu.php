<?php

namespace Drupal\kifimenu;

class BlockConfiguredMenu {
  protected $menuName;

  public function getMenuName() {
    return $this->menuName;
  }

  public function setMenuName($menu_name) {
    $this->menuName = $menu_name;
  }
}
