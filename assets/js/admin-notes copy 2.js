/**
 * admin-notes.js - Modernized and refactored for wp-dashboard-admin-notes
 *
 * Goals:
 *  - ES6+ style, modular helpers
 *  - Use fetch() instead of XMLHttpRequest
 *  - Fewer DOM queries, event delegation for performance
 *  - Debounced saves to reduce server requests
 *  - Improved drag & drop UX for board and checklist
 *  - WordPress.org review friendly: uses nonce from localized AdminNotes object,
 *    no inline exec/eval, no global leaks, same-origin fetch calls
 *
 * Expects a localized global object `AdminNotes`:
 *  AdminNotes = {
 *    ajax_url: 'https://example.com/wp-admin/admin-ajax.php',
 *    nonce: 'abc123'
 *  }
 *
 * Note: Keep all strings escaped on server-side. This file does not perform escaping
 * of server-returned HTML. Server must supply safe HTML or JSON.
 */

( () => {
	/* eslint-env browser */
	'use strict';

	/* -------------------------
	 * Utilities & small helpers
	 * ------------------------- */

	const $qs = (sel, ctx = document) => ctx.querySelector(sel);
	const $qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
	const noop = () => {};

	/**
	 * Simple small debounce helper
	 * @param {Function} fn
	 * @param {number} wait - milliseconds
	 * @returns {Function}
	 */
	const debounce = (fn, wait = 300) => {
		let t = null;
		return (...args) => {
			clearTimeout(t);
			t = setTimeout(() => fn(...args), wait);
		};
	};

	/**
	 * Safe JSON POST to admin-ajax using fetch.
	 * Uses application/x-www-form-urlencoded to mimic typical admin-ajax calls.
	 *
	 * @param {Object} data
	 * @returns {Promise<Object>} parsed JSON or { success: false }
	 */
	const apiPost = async (data = {}) => {
		// ensure AdminNotes is available
		if (typeof AdminNotes === 'undefined' || !AdminNotes.ajax_url) {
			return { success: false, error: 'Missing AdminNotes.ajax_url' };
		}

		// Merge nonce if provided by caller else fallback to AdminNotes.nonce
		if (!data.nonce && AdminNotes.nonce) {
			data.nonce = AdminNotes.nonce;
		}

		// Build urlencoded body
		const body = Object.keys(data)
			.map(k => encodeURIComponent(k) + '=' + encodeURIComponent(
				typeof data[k] === 'string' ? data[k] : JSON.stringify(data[k])
			))
			.join('&');

		try {
			const resp = await fetch(AdminNotes.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body
			});

			if (!resp.ok) {
				return { success: false, status: resp.status };
			}

			// Some handlers may respond with JSON (recommended)
			const json = await resp.json().catch(() => ({ success: false }));
			return json || { success: false };
		} catch (err) {
			// network or parse error
			return { success: false, error: err.message || 'network_error' };
		}
	};

	/**
	 * Render DOM node from HTML string. The server should return well-formed
	 * single-note HTML snippet. Caller must ensure server markup is safe/escaped.
	 *
	 * @param {string} html
	 * @returns {Element|null}
	 */
	const renderNoteFromHTML = (html = '') => {
		const temp = document.createElement('div');
		temp.innerHTML = html.trim();
		return temp.firstElementChild || null;
	};

	/* -------------------------
	 * State & caching
	 * ------------------------- */

	// Keep a simple cache of frequently used DOM roots to avoid repeated queries.
	const DOM = {
		get board() {
			return $qs('#admin-notes-board');
		},
		get addButton() {
			return $qs('#admin-notes-add');
		}
	};

	/* -------------------------
	 * Add New Note - optimized
	 * ------------------------- */

	/**
	 * Handler for creating new note on server and injecting it in DOM.
	 * Debouncing isn't necessary here; we disable the button while the request is in-flight.
	 */
	const handleAddNote = () => {
		const btn = DOM.addButton;
		if (!btn) {
			return;
		}

		btn.addEventListener('click', async (e) => {
			e.preventDefault();

			// Prevent double-clicks or multiple rapid clicks
			if (btn.disabled) return;
			btn.disabled = true;
			const originalText = btn.textContent;

			// Optional: give quick UI feedback
			btn.textContent = originalText + '…';

			const res = await apiPost({ action: 'admin_notes_add' });

			btn.disabled = false;
			btn.textContent = originalText;

			if (res && res.success && res.data && res.data.html) {
				const node = renderNoteFromHTML(res.data.html);
				if (!node) {
					// server returned unexpected data
					alert('Unable to add note (server returned invalid HTML).');
					return;
				}

				// insert at top for newest-first behavior
				const board = DOM.board;
				if (!board) {
					alert('Notes board not found.');
					return;
				}

				board.insertBefore(node, board.firstElementChild);
				// After inserting, we don't rebind all cards; use delegated handlers instead.
				// But some setup (draggable attribute) is needed:
				prepareCardForDnD(node);
				// Save order on server (so insertion is persisted)
				await saveBoardOrder();
			} else {
				alert('Unable to add note.');
			}
		});
	};

	/* -------------------------
	 * Event delegation for card actions
	 * -------------------------
	 *
	 * Instead of binding many listeners per card, prefer delegated listeners
	 * on the board for clicks and input-related events. This reduces memory/time
	 * costs when many cards exist or are added dynamically.
	 * ------------------------- */

	/**
	 * Generic helper to find closest card element from an event target.
	 * @param {EventTarget} t
	 * @returns {Element|null}
	 */
	const closestCard = (t) => t && t.closest ? t.closest('.admin-note-card') : null;

	/**
	 * Save title for a note (debounced)
	 */
	const saveTitle = debounce(async (card) => {
		const noteId = card.getAttribute('data-note-id');
		const input = $qs('.admin-note-title', card);
		if (!noteId || !input) return;
		await apiPost({
			action: 'admin_notes_save_title',
			note_id: noteId,
			title: input.value
		});
	}, 350);

	/**
	 * Save checklist state for a card. This is debounced; invoked after
	 * multiple checklist changes (toggle/add/remove/reorder/edit).
	 */
	const saveChecklistForNote = debounce(async (card) => {
		if (!card) return;
		const noteId = card.getAttribute('data-note-id');
		if (!noteId) return;

		const items = $qsa('.admin-note-check-item', card);
		const arr = items.map((n) => {
			const textEl = n.querySelector('.check-text');
			const chk = n.querySelector('.check-toggle');
			return {
				id: n.getAttribute('data-item-id') || ('i' + Date.now()),
				text: textEl ? textEl.textContent : '',
				completed: chk && chk.checked ? 1 : 0
			};
		});

		await apiPost({
			action: 'admin_notes_save_checklist',
			note_id: noteId,
			checklist: JSON.stringify(arr)
		});
	}, 300);

	/**
	 * Save color for a note (not debounced; instant is fine).
	 * @param {string} noteId
	 * @param {string} color
	 */
	const saveColorForNote = async (noteId, color) => {
		if (!noteId) return;
		await apiPost({
			action: 'admin_notes_save_color',
			note_id: noteId,
			color
		});
	};

	/**
	 * Toggle minimize state for a note and persist.
	 * @param {Element} card
	 */
	const toggleMinimize = async (card) => {
		if (!card) return;
		const body = $qs('.admin-note-body', card);
		const btn = $qs('.admin-note-minimize', card);
		if (!body || !btn) return;

		const collapsed = body.style.display === 'none';
		if (collapsed) {
			body.style.display = '';
			btn.innerHTML = '▼';
		} else {
			body.style.display = 'none';
			btn.innerHTML = '▶';
		}
		// persist toggle
		await apiPost({
			action: 'admin_notes_toggle_minimize',
			note_id: card.getAttribute('data-note-id'),
			state: collapsed ? 0 : 1
		});
	};

	/**
	 * Delete note after confirmation and persist.
	 * @param {Element} card
	 */
	const deleteNote = async (card) => {
		if (!card) return;
		/* eslint-disable no-alert */
		if (!confirm('Delete this note?')) return;
		/* eslint-enable no-alert */

		const noteId = card.getAttribute('data-note-id');
		const res = await apiPost({
			action: 'admin_notes_delete',
			note_id: noteId
		});

		if (res && res.success) {
			card.remove();
			await saveBoardOrder(); // persist new order after deletion
		} else {
			alert('Unable to delete note.');
		}
	};

	/* -------------------------
	 * Checklist item operations via delegation
	 * ------------------------- */

	/**
	 * Create a checklist item element from text. Returns <li>.
	 * @param {string} text
	 * @returns {Element}
	 */
	const createChecklistItem = (text = '') => {
		const li = document.createElement('li');
		li.className = 'admin-note-check-item';
		li.setAttribute('data-item-id', 'i' + Date.now());
		li.setAttribute('draggable', 'true');

		// build controlled markup (avoid innerHTML from server)
		const dragHandle = document.createElement('span');
		dragHandle.className = 'check-drag';
		dragHandle.textContent = '⋮';

		const label = document.createElement('label');
		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.className = 'check-toggle';

		const textSpan = document.createElement('span');
		textSpan.className = 'check-text';
		textSpan.textContent = text;

		label.appendChild(checkbox);
		label.appendChild(textSpan);

		const remBtn = document.createElement('button');
		remBtn.className = 'check-remove';
		remBtn.setAttribute('type', 'button');
		remBtn.textContent = '✕';

		li.appendChild(dragHandle);
		li.appendChild(label);
		li.appendChild(remBtn);

		return li;
	};

	/* -------------------------
	 * Drag & Drop - improved board logic
	 * ------------------------- */

	/**
	 * Prepares a card element for board-level DnD by ensuring attributes are set.
	 * This avoids rebinding many event handlers per card.
	 * @param {Element} card
	 */
	const prepareCardForDnD = (card) => {
		if (!card) return;
		card.setAttribute('draggable', 'true');
	};

	/**
	 * Determine the card after which the dragged element should be placed based on Y coordinate.
	 * Returns null if the element should be appended at end / before first.
	 *
	 * @param {Element} board
	 * @param {number} y - clientY
	 * @returns {Element|null}
	 */
	const getDragAfterElement = (board, y) => {
		const draggableElements = Array.from(board.querySelectorAll('.admin-note-card:not(.dragging)'));
		// Find the closest element where cursor is above half height
		return draggableElements.reduce((closest, child) => {
			const box = child.getBoundingClientRect();
			const offset = y - box.top - box.height / 2;
			if (offset < 0 && offset > closest.offset) {
				return { offset, element: child };
			}
			return closest;
		}, { offset: Number.NEGATIVE_INFINITY }).element || null;
	};

	/**
	 * Set up board-level drag listeners using event delegation on the board container.
	 * This design avoids attaching per-card drag handlers repeatedly.
	 */
	const enableBoardDrag = () => {
		const board = DOM.board;
		if (!board) return;

		let dragSrcCard = null;

		// Use pointer position to determine insertion. We'll still rely on HTML5 DnD API for 'dragging' signaling.
		board.addEventListener('dragstart', (e) => {
			const card = e.target.closest('.admin-note-card');
			if (!card) return;
			dragSrcCard = card;
			card.classList.add('dragging');
			try {
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', card.getAttribute('data-note-id'));
			} catch (err) {
				// ignore fallback
			}
		});

		board.addEventListener('dragover', (e) => {
			e.preventDefault();
			if (!dragSrcCard) return;
			const afterElement = getDragAfterElement(board, e.clientY);
			if (!afterElement) {
				board.appendChild(dragSrcCard);
			} else {
				board.insertBefore(dragSrcCard, afterElement);
			}
		});

		board.addEventListener('drop', (e) => {
			e.preventDefault();
			// drop handled on dragover insertion. Persist new order:
			dragSrcCard && dragSrcCard.classList.remove('dragging');
			dragSrcCard = null;
			saveBoardOrder();
		});

		board.addEventListener('dragend', () => {
			if (dragSrcCard) {
				dragSrcCard.classList.remove('dragging');
				dragSrcCard = null;
			}
		});
	};

	/* -------------------------
	 * Checklist DnD (per-list)
	 * ------------------------- */

	/**
	 * For checklists we keep the original HTML5 DnD but improve insertion logic.
	 * We attach listeners on the list element itself (delegation).
	 *
	 * @param {Element} listEl
	 */
	const enableChecklistDnD = (listEl) => {
		if (!listEl) return;
		let dragSrc = null;

		listEl.addEventListener('dragstart', (e) => {
			const li = e.target.closest('.admin-note-check-item');
			if (!li) return;
			dragSrc = li;
			li.classList.add('dragging');
			try {
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', li.getAttribute('data-item-id'));
			} catch (err) {}
		});

		listEl.addEventListener('dragover', (e) => {
			e.preventDefault();
			const target = e.target.closest('.admin-note-check-item');
			if (!target || target === dragSrc) return;
			// Insert before target for clarity
			const rect = target.getBoundingClientRect();
			const shouldInsertBefore = (e.clientY - rect.top) < rect.height / 2;
			if (shouldInsertBefore) {
				listEl.insertBefore(dragSrc, target);
			} else {
				listEl.insertBefore(dragSrc, target.nextSibling);
			}
		});

		listEl.addEventListener('drop', (e) => {
			e.preventDefault();
			if (dragSrc) {
				dragSrc.classList.remove('dragging');
				dragSrc = null;
				// persist
				const card = e.target.closest('.admin-note-card');
				card && saveChecklistForNote(card);
			}
		});

		listEl.addEventListener('dragend', () => {
			if (dragSrc) {
				dragSrc.classList.remove('dragging');
				dragSrc = null;
			}
		});
	};

	/* -------------------------
	 * Persist board order
	 * ------------------------- */

	const saveBoardOrder = debounce(async () => {
		const board = DOM.board;
		if (!board) return;
		const cards = $qsa('.admin-note-card', board);
		const ids = cards.map(c => parseInt(c.getAttribute('data-note-id'), 10)).filter(n => !Number.isNaN(n));
		await apiPost({
			action: 'admin_notes_save_order',
			order: JSON.stringify(ids)
		});
	}, 250);

	/* -------------------------
	 * Delegated event listeners on board
	 * ------------------------- */

	/**
	 * Handle keyboard add-list-item events (Enter on input)
	 * Delegated to board so dynamically added inputs work automatically.
	 */
	const boardKeydownHandler = (e) => {
		const input = e.target;
		if (!input || !input.classList || !input.classList.contains('admin-note-add-input')) return;

		if (e.key === 'Enter') {
			e.preventDefault();
			const text = input.value.trim();
			if (!text) return;
			const card = closestCard(input);
			if (!card) return;

			const list = $qs('.admin-note-checklist', card);
			if (!list) return;

			// create new LI and append
			const li = createChecklistItem(text);
			list.appendChild(li);
			input.value = '';
			// enable dnd on this list item by virtue of list-level listeners
			// persist
			saveChecklistForNote(card);
		}
	};

	/**
	 * Board-level click handler for actions: delete, minimize, color swatch, color picker, remove checklist item
	 * Delegation keeps binding cost low
	 */
	const boardClickHandler = async (e) => {
		const target = e.target;

		// delete button on card
		if (target.matches('.admin-note-delete')) {
			const card = closestCard(target);
			card && deleteNote(card);
			return;
		}

		// minimize toggle
		if (target.matches('.admin-note-minimize')) {
			const card = closestCard(target);
			card && toggleMinimize(card);
			return;
		}

		// color swatch
		if (target.matches('.admin-note-color-swatch')) {
			const card = closestCard(target);
			const color = target.getAttribute('data-color');
			if (card && color) {
				card.style.background = color;
				await saveColorForNote(card.getAttribute('data-note-id'), color);
			}
			return;
		}

		// checklist remove item
		if (target.matches('.check-remove')) {
			const li = target.closest('.admin-note-check-item');
			const card = closestCard(target);
			if (li && card) {
				li.remove();
				saveChecklistForNote(card);
			}
			return;
		}

		// clicking on check-drag (no action needed — DnD handled by list-level listeners)
	};

	/**
	 * Board-level input handler for title changes and color picker inputs
	 */
	const boardInputHandler = (e) => {
		const target = e.target;

		// title input changes (debounced save)
		if (target.matches('.admin-note-title')) {
			const card = closestCard(target);
			card && saveTitle(card);
			return;
		}

		// color picker input (instant)
		if (target.matches('.admin-note-color-picker')) {
			const card = closestCard(target);
			const color = target.value;
			if (card) {
				card.style.background = color;
				saveColorForNote(card.getAttribute('data-note-id'), color);
			}
		}

		// checkbox toggle for checklist items
		if (target.matches('.check-toggle')) {
			const card = closestCard(target);
			card && saveChecklistForNote(card);
		}
	};

	/**
	 * Board-level click to enable inline editing of checklist text.
	 * When clicking .check-text we replace with an input and handle blur/enter.
	 */
	const boardClickInlineEditHandler = (e) => {
		const target = e.target;
		if (!target.matches('.check-text')) return;
		const current = target.textContent || '';
		const input = document.createElement('input');
		input.type = 'text';
		input.value = current;
		input.className = 'check-text-input';
		input.style.width = '100%';

		// swap elements
		target.replaceWith(input);
		input.focus();

		const commit = () => {
			const newVal = input.value.trim() || current;
			target.textContent = newVal;
			input.replaceWith(target);
			const card = closestCard(target);
			card && saveChecklistForNote(card);
		};

		input.addEventListener('blur', commit, { once: true });
		input.addEventListener('keydown', (ev) => {
			if (ev.key === 'Enter') {
				ev.preventDefault();
				commit();
			} else if (ev.key === 'Escape') {
				// cancel edit
				input.replaceWith(target);
			}
		});
	};

	/* -------------------------
	 * Initialization
	 * ------------------------- */

	/**
	 * Bind existing cards for checklist DnD and prepare attributes.
	 * Use minimal per-card binding: set draggable attribute and checklist DnD on each list.
	 */
	const initBoard = () => {
		const board = DOM.board;
		if (!board) return;

		// Prepare any existing cards
		const cards = $qsa('.admin-note-card', board);
		cards.forEach((card) => {
			prepareCardForDnD(card);

			// ensure each checklist list has DnD listeners
			const lists = $qsa('.admin-note-checklist', card);
			lists.forEach((list) => enableChecklistDnD(list));
		});

		// Attach delegated handlers once
		board.addEventListener('click', boardClickHandler);
		board.addEventListener('input', boardInputHandler);
		board.addEventListener('keydown', boardKeydownHandler);
		board.addEventListener('click', boardClickInlineEditHandler);

		// Board-level drag behavior
		enableBoardDrag();
	};

	/* -------------------------
	 * Boot
	 * ------------------------- */

	document.addEventListener('DOMContentLoaded', () => {
		handleAddNote();
		initBoard();
	});

})();
