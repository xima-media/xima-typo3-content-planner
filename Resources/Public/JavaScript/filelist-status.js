/**
 * Module: @xima/ximatypo3contentplanner/filelist-status
 *
 * Handles status selection for files and folders in the TYPO3 Filelist module.
 */
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Icons from "@typo3/backend/icons.js";
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class FilelistStatus {
  statusOptions = null;

  constructor() {
    if (this.isFilelistModule()) {
      this.initialize();
    }
  }

  isFilelistModule() {
    const moduleElement = document.querySelector('[data-module-name="media_management"]');
    return moduleElement !== null || window.location.href.includes('module=media_management');
  }

  async initialize() {
    await this.loadStatusOptions();
    this.addStatusDropdowns();
  }

  async loadStatusOptions() {
    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_filelist_status_options).get();
      const data = await response.resolve();
      this.statusOptions = data.statuses || [];
    } catch (error) {
      console.error('Failed to load status options:', error);
      this.statusOptions = [];
    }
  }

  addStatusDropdowns() {
    // Add dropdowns to file rows (list view)
    this.addDropdownsToFiles();
    // Add dropdowns to folder rows (list view)
    this.addDropdownsToFolders();
    // Handle tiles view
    this.addDropdownsToTiles();
  }

  addDropdownsToFiles() {
    const fileRows = document.querySelectorAll('tr[data-filelist-meta-uid]');
    fileRows.forEach(row => {
      const metaUid = row.getAttribute('data-filelist-meta-uid');
      const btnGroup = row.querySelector('.btn-group');
      if (btnGroup && metaUid) {
        this.insertDropdown(btnGroup, 'file', metaUid, null);
      }
    });
  }

  addDropdownsToFolders() {
    const folderRows = document.querySelectorAll('tr[data-filelist-identifier]');
    folderRows.forEach(row => {
      const identifier = row.getAttribute('data-filelist-identifier');
      // Skip if this is a file (has meta-uid)
      if (row.hasAttribute('data-filelist-meta-uid')) {
        return;
      }
      const btnGroup = row.querySelector('.btn-group');
      if (btnGroup && identifier) {
        this.insertDropdown(btnGroup, 'folder', null, identifier);
      }
    });
  }

  addDropdownsToTiles() {
    // Files in tiles view
    const fileTiles = document.querySelectorAll('.filelist-tile[data-filelist-meta-uid]');
    fileTiles.forEach(tile => {
      const metaUid = tile.getAttribute('data-filelist-meta-uid');
      const actionBar = tile.querySelector('.filelist-tile-actions');
      if (actionBar && metaUid) {
        this.insertDropdown(actionBar, 'file', metaUid, null, true);
      }
    });

    // Folders in tiles view
    const folderTiles = document.querySelectorAll('.filelist-tile[data-filelist-identifier]:not([data-filelist-meta-uid])');
    folderTiles.forEach(tile => {
      const identifier = tile.getAttribute('data-filelist-identifier');
      const actionBar = tile.querySelector('.filelist-tile-actions');
      if (actionBar && identifier) {
        this.insertDropdown(actionBar, 'folder', null, identifier, true);
      }
    });
  }

  async insertDropdown(container, type, metaUid, identifier, isTilesView = false) {
    const dropdownContainer = document.createElement('div');
    dropdownContainer.className = 'btn-group';
    dropdownContainer.innerHTML = `
      <button type="button" class="btn btn-default btn-sm dropdown-toggle contentplanner-status-dropdown"
              data-bs-toggle="dropdown" aria-expanded="false"
              data-type="${type}"
              ${metaUid ? `data-meta-uid="${metaUid}"` : ''}
              ${identifier ? `data-identifier="${identifier}"` : ''}
              title="Content Planner Status">
        <span class="contentplanner-status-icon"></span>
      </button>
      <ul class="dropdown-menu contentplanner-status-menu"></ul>
    `;

    // Load initial icon
    const iconContainer = dropdownContainer.querySelector('.contentplanner-status-icon');
    const flagIcon = await Icons.getIcon('flag-gray', Icons.sizes.small);
    iconContainer.innerHTML = flagIcon;

    // Populate dropdown menu
    const menu = dropdownContainer.querySelector('.contentplanner-status-menu');
    await this.populateDropdownMenu(menu, type, metaUid, identifier);

    // Insert at the beginning of the button group
    container.insertBefore(dropdownContainer, container.firstChild);

    // Add click handler for dropdown items
    menu.addEventListener('click', async (event) => {
      const item = event.target.closest('.dropdown-item');
      if (item) {
        event.preventDefault();
        const statusUid = parseInt(item.getAttribute('data-status-uid'), 10);
        await this.changeStatus(type, metaUid, identifier, statusUid);
      }
    });
  }

  async populateDropdownMenu(menu, type, metaUid, identifier) {
    if (!this.statusOptions || this.statusOptions.length === 0) {
      menu.innerHTML = '<li><span class="dropdown-item disabled">No statuses available</span></li>';
      return;
    }

    let html = '';
    for (const status of this.statusOptions) {
      const icon = await Icons.getIcon(status.icon || 'flag-gray', Icons.sizes.small);
      html += `<li><a class="dropdown-item" href="#" data-status-uid="${status.uid}">${icon} ${this.escapeHtml(status.title)}</a></li>`;
    }
    menu.innerHTML = html;
  }

  async changeStatus(type, metaUid, identifier, statusUid) {
    try {
      let url;
      let data;

      if (type === 'file') {
        url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_file_status_update;
        data = { metaUid: metaUid, status: statusUid };
      } else {
        url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_folder_status_update;
        data = { identifier: identifier, status: statusUid };
      }

      const response = await new AjaxRequest(url).post(data);
      const result = await response.resolve();

      if (result.success) {
        Notification.message(
          statusUid === 0 ? 'status.reset' : 'status.changed',
          'success'
        );
        // Reload page to reflect changes
        window.location.reload();
      } else {
        Notification.message('status.changed', 'failure');
        console.error('Failed to change status:', result);
      }
    } catch (error) {
      Notification.message('status.changed', 'failure');
      console.error('Error changing status:', error);
    }
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

export default new FilelistStatus();
