<?php

final class PhutilAuthAdapterRemoteUser extends PhutilAuthAdapter {

  var $extended_user_login_name;
  var $extended_user_real_name;
  var $extended_user_mail;

  var $ldap_params;

  function __construct() {
    $this->ldap_params = parse_ini_file("ldap_params.ini");
  }

  public function getProviderName() {
    return pht('RemoteUser');
  }

  public function getDescriptionForCreate() {
    return pht('Configure a connection to use web server authentication credentials to log in to Phabricator.');
  }

  public function getAdapterDomain() {
    return 'self';
  }

  public function getAdapterType() {
    return 'RemoteUser';
  }

  public function getAccountID() {
    $this->extended_user_login_name = "";
    $this->extended_user_real_name = "";
    $this->extended_user_mail = "";
    if (!empty($_SERVER['REMOTE_USER'])) {
      $l_remote_user_piece = explode("@", $_SERVER['REMOTE_USER']);
      if (count($l_remote_user_piece) == 2) {
        if ($l_remote_user_piece[1] == $this->ldap_params["domain"]) {
          $this->extended_user_login_name = $l_remote_user_piece[0];
        }
      }
    }
    return $this->extended_user_login_name;
  }

  public function lookupUserLDAP() {
    $this->extended_user_real_name = "";
    $this->extended_user_mail = "";
    $l_ldap_uri = $this->ldap_params["ldap_uri"];
    $l_attribute_user_id = $this->ldap_params["attribute_user_id"];
    $l_attribute_user_name = $this->ldap_params["attribute_user_name"];
    $l_attribute_user_mail = $this->ldap_params["attribute_user_mail"];
    $l_search_base_dn = $this->ldap_params["search_base_dn"];
    $l_search_extra_filter = $this->ldap_params["search_extra_filter"];
    if (!empty($this->extended_user_login_name) and !empty($l_ldap_uri) and !empty($l_attribute_user_id) and !empty($l_attribute_user_name) and !empty($l_attribute_user_mail)) {
      $ds = ldap_connect($l_ldap_uri);
      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
      $r = ldap_bind($ds, $this->ldap_params["bind_rdn"], $this->ldap_params["bind_password"]);
      if ($r) {
        $sr = ldap_search($ds, $l_search_base_dn, "(&(" . $l_attribute_user_id . "=" . $this->extended_user_login_name . ")" . $l_search_extra_filter . ")", array($l_attribute_user_name, $l_attribute_user_mail));
        $l_user_entry = ldap_get_entries($ds, $sr);
        if ($l_user_entry["count"] == 1) {
          $l_user = $l_user_entry[0];
          $this->extended_user_real_name = $l_user[$l_attribute_user_name][0];
          $this->extended_user_mail = $l_user[$l_attribute_user_mail][0];
        }
        ldap_close($ds);
      }
    }
  }

  public function getAccountName() {
    return $this->getAccountID();
  }

  public function getAccountRealName() {
    if (empty($this->extended_user_real_name))
      $this->lookupUserLDAP();
    if (!empty($this->extended_user_real_name))
      return $this->extended_user_real_name;
    else
      return parent::getAccountRealName();
  }

  public function getAccountEmail() {
    if (empty($this->extended_user_mail))
      $this->lookupUserLDAP();
    if (!empty($this->extended_user_mail))
      return $this->extended_user_mail;
    else
      return parent::getAccountEmail();
  }

  public function getAccountURI() {
    return parent::getAccountURI();
  }

}
