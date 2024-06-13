(() => {
	/**
	 * Decode function for HTML entities
	 *
	 * @param {string} input
	 * @returns 
	 */
	 const htmlDecode = input => {
		const doc = new DOMParser().parseFromString(input, "text/html");
		return doc.documentElement.textContent;
	}

	/**
	 * Shows overlay element with a message from the ajax response
	 *
	 * @param {string} message
	 */
	const showMessage = message => {
		const overlayDiv = document.createElement('div');
		const overlayContent = document.createTextNode(message);
		overlayDiv.setAttribute('id', 'nine3editor');
		overlayDiv.appendChild(overlayContent);
		document.body.appendChild(overlayDiv);

		// Destroy element after 3 seconds.
		setTimeout(() => {
			overlayDiv.remove();
		}, 3000);
	};

	/**
	 * Get data attributes from element and send XMLHttpRequest
	 * The PHP script does all the legwork of adding meta and creating a post
	 * Then sends back a URL so we can redirect to it
	 *
	 * @param {HTMLElement} element
	 */
	const triggerAddEditor = element => {
		const source = element.dataset.source;
		const type = element.dataset.type;
		const data = element.dataset.editor;

		const xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function () {
			if (this.readyState == 4) {
				const response = JSON.parse(xhttp.response);
				showMessage(response.message);
				if (response.success && response.target) {
					window.location.href = htmlDecode(response.target);
				}
			}
		};

		xhttp.open('GET', `${nine3_editor.ajaxurl}?action=nine3-add-editor&nonce=${nine3_editor.nonce}&source=${source}&type=${type}&data=${data}`);
		xhttp.send();
	};

	/**
	 * Get data attributes from element and send XMLHttpRequest
	 * The PHP script removes the target post and deletes source meta
	 *
	 * @param {HTMLElement} element
	 */
	const triggerDeleteEditor = element => {
		const source = element.dataset.source;
		const target = element.dataset.target;
		const type = element.dataset.type;

		const xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function () {
			if (this.readyState == 4) {
				const response = JSON.parse(xhttp.response);
				showMessage(response.message);
				if (response.success) {
					location.reload();
				}
			}
		};

		xhttp.open('GET', `${nine3_editor.ajaxurl}?action=nine3-delete-editor&nonce=${nine3_editor.nonce}&source=${source}&target=${target}&type=${type}`);
		xhttp.send();
	};

	// Add button.
	const addButton = document.querySelector('.nine3-editor .add-editor');
	if (addButton) {
		addButton.addEventListener('click', (e) => {
			e.preventDefault();
			triggerAddEditor(e.target);
		});
	}

	// Delete button.
	const deleteButton = document.querySelector('.nine3-editor .delete-editor');
	if (deleteButton) {
		deleteButton.addEventListener('click', (e) => {
			e.preventDefault();
			let deleteString = prompt('This will permanently delete the editor page, please type "DELETE" to confirm', '');
			if (deleteString != 'DELETE') {
				return;
			}
			triggerDeleteEditor(e.target);
		});
	}
})();