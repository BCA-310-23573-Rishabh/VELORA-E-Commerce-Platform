// session.js — tab utilities (does NOT override fetch or break PHP sessions)
// Normal PHP cookie-based sessions work fine across tabs.
// This file just provides helper utilities.

(function () {
  // Expose a tab ID for UI purposes (not used for auth)
  const TAB_KEY = 'veloraTabId';
  let tabId = sessionStorage.getItem(TAB_KEY);
  if (!tabId) {
    tabId = 'tab_' + Math.random().toString(36).substr(2, 9);
    sessionStorage.setItem(TAB_KEY, tabId);
  }
  window.veloraTabId = tabId;
})();
