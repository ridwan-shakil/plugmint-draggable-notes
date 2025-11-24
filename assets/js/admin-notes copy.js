/* Admin Notes minimal JS (vanilla)
   checklist reordering and note drag/drop ordering
*/
( function () {
	'use strict';

	// Utilities
	function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	
	function ajaxPost(data, cb) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', AdminNotes.ajax_url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.onload = function () {
			if (xhr.status >= 200 && xhr.status < 400) {
				try {
					var json = JSON.parse(xhr.responseText);
					cb(json);
				} catch (e) {
					cb({ success: false });
				}
			} else {
				cb({ success: false });
			}
		};
		var params = [];
		for (var k in data) {
			if (Object.prototype.hasOwnProperty.call(data, k)) {
				params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
			}
		}
		xhr.send(params.join('&'));
	}

	// Render helpers
	function renderNoteFromHTML(html) {
		var template = document.createElement('div');
		template.innerHTML = html;
		return template.firstElementChild;
	}

	// Add new note
	function handleAddNote() {
		var btn = qs('#admin-notes-add');
		if (!btn) { return; }
		btn.addEventListener('click', function (e) {
			btn.disabled = true;
			ajaxPost({
				action: 'admin_notes_add',
				nonce: AdminNotes.nonce
			}, function (res) {
				btn.disabled = false;
				if (res && res.success && res.data && res.data.html) {
					var board = qs('#admin-notes-board');
					var node = renderNoteFromHTML(res.data.html);
					// insert at beginning
					if (board.firstChild) {
						board.insertBefore(node, board.firstChild);
					} else {
						board.appendChild(node);
					}
					bindCard(node);
					// update board drag bindings
					enableBoardDrag();
				} else {
					alert('Unable to add note');
				}
			});
		});
	}

	// Bind events for a card DOM node
	function bindCard(card) {
		if (!card) { return; }
		var noteId = card.getAttribute('data-note-id');

		// Title save on blur/enter
		var titleInput = card.querySelector('.admin-note-title');
		if (titleInput) {
			var saveTitle = function () {
				var val = titleInput.value;
				ajaxPost({
					action: 'admin_notes_save_title',
					note_id: noteId,
					title: val,
					nonce: AdminNotes.nonce
				}, function(){});
			};
			titleInput.addEventListener('blur', saveTitle);
			titleInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					titleInput.blur();
				}
			});
		}

		// Delete
		var del = card.querySelector('.admin-note-delete');
		if (del) {
			del.addEventListener('click', function () {
				if (!confirm('Delete this note?')) { return; }
				ajaxPost({
					action: 'admin_notes_delete',
					note_id: noteId,
					nonce: AdminNotes.nonce
				}, function (res) {
					if (res && res.success) {
						card.parentNode.removeChild(card);
						// After removal update order
						saveBoardOrder();
					} else {
						alert('Unable to delete note');
					}
				});
			});
		}

		// Minimize toggle
		var minBtn = card.querySelector('.admin-note-minimize');
		var body = card.querySelector('.admin-note-body');
		if (minBtn && body) {
			minBtn.addEventListener('click', function () {
				var collapsed = body.style.display === 'none';
				if (collapsed) {
					body.style.display = '';
					minBtn.innerHTML = '&#9660;';
				} else {
					body.style.display = 'none';
					minBtn.innerHTML = '&#9654;';
				}
				ajaxPost({
					action: 'admin_notes_toggle_minimize',
					note_id: noteId,
					state: !collapsed ? 1 : 0,
					nonce: AdminNotes.nonce
				}, function () {});
			});
		}

		// Add checklist item
		var addInput = card.querySelector('.admin-note-add-input');
		var checklist = card.querySelector('.admin-note-checklist');
		if (addInput && checklist) {
			addInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					var text = addInput.value.trim();
					if (!text) { return; }
					// create item element
					var itemId = 'i' + Date.now();
					var li = document.createElement('li');
					li.className = 'admin-note-check-item';
					li.setAttribute('data-item-id', itemId);
					li.setAttribute('draggable', 'true');
					li.innerHTML = '<span class="check-drag">⋮</span><label><input type="checkbox" class="check-toggle" /><span class="check-text"></span></label><button class="check-remove">✕</button>';
					li.querySelector('.check-text').textContent = text;
					checklist.appendChild(li);
					addInput.value = '';
					// save checklist to server
					saveChecklistForNote(card);
					// bind newly created controls
					bindCheckItem(li, card);
				}
			});
		}

		// Bind existing checklist items
		var items = qsa('.admin-note-check-item', card);
		items.forEach(function (it) {
			it.setAttribute('draggable', 'true');
			bindCheckItem(it, card);
		});

		// Checklist drag/drop handlers (using HTML5 DnD)
		enableChecklistDnD(checklist, card);

		// Color swatches & picker
		var swatches = qsa('.admin-note-color-swatch', card);
		swatches.forEach(function (s) {
			s.addEventListener('click', function () {
				var color = s.getAttribute('data-color');
				card.style.background = color;
				saveColorForNote(noteId, color);
			});
		});
		var picker = card.querySelector('.admin-note-color-picker');
		if (picker) {
			picker.addEventListener('input', function () {
				var color = picker.value;
				card.style.background = color;
				saveColorForNote(noteId, color);
			});
		}
	}

	// Bind checklist item controls
	function bindCheckItem(li, card) {
		var noteId = card.getAttribute('data-note-id');
		var chk = li.querySelector('.check-toggle');
		var rem = li.querySelector('.check-remove');
		var textSpan = li.querySelector('.check-text');

		// Toggle complete
		if (chk) {
			chk.addEventListener('change', function () {
				// style strike
				if (chk.checked) {
					textSpan.style.textDecoration = 'line-through';
					textSpan.style.opacity = '0.6';
				} else {
					textSpan.style.textDecoration = '';
					textSpan.style.opacity = '';
				}
				saveChecklistForNote(card);
			});
		}

		// Remove
		if (rem) {
			rem.addEventListener('click', function () {
				li.parentNode.removeChild(li);
				saveChecklistForNote(card);
			});
		}

		// Edit label on click -> simple inline edit
		if (textSpan) {
			textSpan.addEventListener('click', function () {
				var current = textSpan.textContent;
				var input = document.createElement('input');
				input.type = 'text';
				input.value = current;
				input.style.width = '100%';
				textSpan.replaceWith(input);
				input.focus();
				input.addEventListener('blur', function () {
					var newVal = input.value.trim();
					if (!newVal) { newVal = current; }
					textSpan.textContent = newVal;
					input.replaceWith(textSpan);
					saveChecklistForNote(card);
				});
				input.addEventListener('keydown', function (e) {
					if (e.key === 'Enter') {
						input.blur();
					}
				});
			});
		}
	}

	// Collect checklist items and POST to server
	function saveChecklistForNote(card) {
		var noteId = card.getAttribute('data-note-id');
		var nodes = qsa('.admin-note-check-item', card);
		var arr = nodes.map(function (n) {
			return {
				id: n.getAttribute('data-item-id') || ('i' + Date.now()),
				text: (n.querySelector('.check-text') && n.querySelector('.check-text').textContent) || '',
				completed: n.querySelector('.check-toggle') && n.querySelector('.check-toggle').checked ? 1 : 0
			};
		});
		ajaxPost({
			action: 'admin_notes_save_checklist',
			note_id: noteId,
			checklist: JSON.stringify(arr),
			nonce: AdminNotes.nonce
		}, function () {});
	}

	function saveColorForNote(noteId, color) {
		ajaxPost({
			action: 'admin_notes_save_color',
			note_id: noteId,
			color: color,
			nonce: AdminNotes.nonce
		}, function () {});
	}

	// ---------------------------
	// Board drag & drop (notes)
	// ---------------------------
	var boardDragSrc = null;

	function enableBoardDrag() {
		var board = qs('#admin-notes-board');
		if (!board) { return; }
		// ensure each card is draggable and has listeners
		var cards = qsa('.admin-note-card', board);
		cards.forEach(function (card) {
			card.setAttribute('draggable', 'true');

			// skip if handlers already set (prevent duplicate)
			if (card._adminNotesDnD) { return; }
			card._adminNotesDnD = true;

			card.addEventListener('dragstart', function (e) {
				boardDragSrc = card;
				card.classList.add('dragging');
				try {
					e.dataTransfer.effectAllowed = 'move';
					// required for Firefox
					e.dataTransfer.setData('text/plain', card.getAttribute('data-note-id'));
				} catch (err) {
					// ignore
				}
			});

			card.addEventListener('dragover', function (e) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				// visually indicate position
				card.classList.add('drag-over');
			});

			card.addEventListener('dragleave', function (e) {
				card.classList.remove('drag-over');
			});

			card.addEventListener('drop', function (e) {
				e.preventDefault();
				card.classList.remove('drag-over');
				if (!boardDragSrc || boardDragSrc === card) {
					boardDragSrc = null;
					if (boardDragSrc) boardDragSrc.classList.remove('dragging');
					return;
				}
				// Insert before dropped-on card
				board.insertBefore(boardDragSrc, card);
				// Clean up class
				if (boardDragSrc) boardDragSrc.classList.remove('dragging');
				boardDragSrc = null;
				// Save new order
				saveBoardOrder();
			});

			card.addEventListener('dragend', function () {
				card.classList.remove('dragging');
				// remove any drag-over classes left behind
				var others = qsa('.drag-over', board);
				others.forEach(function (o) { o.classList.remove('drag-over'); });
			});
		});
	}

	// Save board order: collect note IDs in DOM order and POST
	function saveBoardOrder() {
		var board = qs('#admin-notes-board');
		if (!board) { return; }
		var cards = qsa('.admin-note-card', board);
		var ids = cards.map(function (c) {
			return parseInt(c.getAttribute('data-note-id'), 10);
		}).filter(function (n) { return !isNaN(n); });

		ajaxPost({
			action: 'admin_notes_save_order',
			order: JSON.stringify(ids),
			nonce: AdminNotes.nonce
		}, function () {});
	}

	// ---------------------------
	// Checklist drag & drop
	// ---------------------------
	function enableChecklistDnD(listEl, card) {
		if (!listEl) { return; }

		var dragSrcEl = null;

		listEl.addEventListener('dragstart', function (e) {
			var li = e.target.closest('.admin-note-check-item');
			if (!li) { return; }
			dragSrcEl = li;
			li.classList.add('dragging');
			try {
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', li.getAttribute('data-item-id'));
			} catch (err) {}
		});

		listEl.addEventListener('dragover', function (e) {
			e.preventDefault();
			var target = e.target.closest('.admin-note-check-item');
			if (target && target !== dragSrcEl) {
				target.classList.add('drag-over');
			}
		});

		listEl.addEventListener('dragleave', function (e) {
			var target = e.target.closest('.admin-note-check-item');
			if (target) target.classList.remove('drag-over');
		});

		listEl.addEventListener('drop', function (e) {
			e.preventDefault();
			var target = e.target.closest('.admin-note-check-item');
			if (!target || !dragSrcEl) { return; }
			target.classList.remove('drag-over');
			if (dragSrcEl === target) { return; }

			// Insert dragSrc before target
			listEl.insertBefore(dragSrcEl, target);
			dragSrcEl.classList.remove('dragging');
			// save checklist
			saveChecklistForNote(card);
		});

		listEl.addEventListener('dragend', function (e) {
			if (dragSrcEl) {
				dragSrcEl.classList.remove('dragging');
				dragSrcEl = null;
			}
			var items = qsa('.admin-note-check-item', listEl);
			items.forEach(function (it) { it.classList.remove('drag-over'); });
		});
	}

	// Initialize all existing cards
	function initBoard() {
		var cards = qsa('.admin-note-card');
		cards.forEach(function (c) {
			bindCard(c);
		});
		enableBoardDrag();
	}

	// Kick off
	document.addEventListener('DOMContentLoaded', function () {
		handleAddNote();
		initBoard();
	});
})();
