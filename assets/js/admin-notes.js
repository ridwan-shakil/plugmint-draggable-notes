/**
 * Admin Notes – jQuery Version (WordPress.org Ready)
 */

jQuery(function ($) {

	// -----------------------------
	// AJAX helper
	// -----------------------------
	function postAjax(data, callback) {
		$.post(AdminNotes.ajax_url, data, function (response) {
			if (typeof callback === "function") {
				callback(response);
			}
		}, "json");
	}

	// -----------------------------
	// Add new note
	// -----------------------------
	$("#admin-notes-add").on("click", function () {
		const $btn = $(this);
		$btn.prop("disabled", true);

		postAjax({
			action: "admin_notes_add",
			nonce: AdminNotes.nonce
		}, function (res) {

			$btn.prop("disabled", false);

			if (!res || !res.success || !res.data.html) {
				alert("Unable to add note");
				return;
			}

			// Convert HTML → jQuery object quickly
			const $newCard = $(res.data.html);

			// Insert at top
			$("#admin-notes-board").prepend($newCard);

			bindCard($newCard);
			refreshBoardSortable();
		});
	});

	// -----------------------------
	// Bind card events
	// -----------------------------
	function bindCard($card) {

		const noteID = $card.data("note-id");

		/** ------------------------
		 * Title editing
		 * ---------------------- */
		$card.find(".admin-note-title")
			.on("blur", saveTitle)
			.on("keydown", function (e) {
				if (e.key === "Enter") {
					e.preventDefault();
					$(this).blur();
				}
			});

		function saveTitle() {
			postAjax({
				action: "admin_notes_save_title",
				note_id: noteID,
				nonce: AdminNotes.nonce,
				title: $(this).val()
			});
		}

		/** ------------------------
		 * Delete card
		 * ---------------------- */
		$card.find(".admin-note-delete").on("click", function () {
			if (!confirm("Delete this note?")) return;

			postAjax({
				action: "admin_notes_delete",
				nonce: AdminNotes.nonce,
				note_id: noteID
			}, function (res) {
				if (res.success) {
					$card.remove();
					saveBoardOrder();
				}
			});
		});

		/** ------------------------
		 * Minimize
		 * ---------------------- */
		$card.find(".admin-note-minimize").on("click", function () {
			const $body = $card.find(".admin-note-body");
			const isClosed = $body.is(":visible");

			$body.toggle();
			$(this).html(isClosed ? "&#9654;" : "&#9660;");

			postAjax({
				action: "admin_notes_toggle_minimize",
				note_id: noteID,
				state: isClosed ? 1 : 0,
				nonce: AdminNotes.nonce
			});
		});

		/** ------------------------
		 * Checklist: Add item
		 * ---------------------- */
		$card.find(".admin-note-add-input").on("keydown", function (e) {
			if (e.key !== "Enter") return;

			const txt = $(this).val().trim();
			if (!txt) return;

			const itemID = "i" + Date.now();
			const $li = $(`
				<li class="admin-note-check-item" data-item-id="${itemID}">
					<span class="check-drag">⋮</span>
					<label>
						<input type="checkbox" class="check-toggle">
						<span class="check-text">${txt}</span>
					</label>
					<button class="check-remove">✕</button>
				</li>
			`);

			$(this).val("");

			const $list = $card.find(".admin-note-checklist");
			$list.append($li);

			bindCheckItem($li, $card);
			saveChecklist($card);

			refreshChecklistSortable($list);
		});

		/** ------------------------
		 * Bind existing checklist items
		 * ---------------------- */
		$card.find(".admin-note-check-item").each(function () {
			bindCheckItem($(this), $card);
		});

		// enable sortable checklist
		refreshChecklistSortable($card.find(".admin-note-checklist"));

		/** ------------------------
		 * Color picker
		 * ---------------------- */
		$card.find(".admin-note-color-swatch").on("click", function () {
			const color = $(this).data("color");
			$card.css("background", color);

			postAjax({
				action: "admin_notes_save_color",
				note_id: noteID,
				color,
				nonce: AdminNotes.nonce
			});
		});

		$card.find(".admin-note-color-picker").on("input", function () {
			const color = $(this).val();
			$card.css("background", color);

			postAjax({
				action: "admin_notes_save_color",
				note_id: noteID,
				color,
				nonce: AdminNotes.nonce
			});
		});
	}

	// -----------------------------
	// Handle checklist item
	// -----------------------------
	function bindCheckItem($li, $card) {
		const $toggle = $li.find(".check-toggle");
		const $text = $li.find(".check-text");

		/** toggle */
		$toggle.on("change", function () {
			if (this.checked) {
				$text.css({ textDecoration: "line-through", opacity: 0.6 });
			} else {
				$text.css({ textDecoration: "", opacity: 1 });
			}
			saveChecklist($card);
		});

		/** remove */
		$li.find(".check-remove").on("click", function () {
			$li.remove();
			saveChecklist($card);
		});

		/** inline edit */
		$text.on("click", function () {
			const old = $text.text();
			const $input = $(`<input type="text" value="${old}" class="check-edit">`);

			$text.replaceWith($input);
			$input.focus();

			$input.on("blur keydown", function (e) {
				if (e.type === "keydown" && e.key !== "Enter") return;

				const val = $input.val().trim() || old;
				const $newText = $(`<span class="check-text">${val}</span>`);

				$input.replaceWith($newText);
				bindCheckItem($li, $card);

				saveChecklist($card);
			});
		});
	}

	// -----------------------------
	// Save checklist to server
	// -----------------------------
	function saveChecklist($card) {
		const noteID = $card.data("note-id");
		const data = [];

		$card.find(".admin-note-check-item").each(function () {
			data.push({
				id: $(this).data("item-id"),
				text: $(this).find(".check-text").text(),
				completed: $(this).find(".check-toggle").is(":checked") ? 1 : 0
			});
		});

		postAjax({
			action: "admin_notes_save_checklist",
			nonce: AdminNotes.nonce,
			note_id: noteID,
			checklist: JSON.stringify(data)
		});
	}

	// -----------------------------
	// NOTE DRAGGING (jQuery UI Sortable)
	// -----------------------------
	function refreshBoardSortable() {
		$("#admin-notes-board").sortable({
			handle: ".admin-note-header",
			placeholder: "admin-note-placeholder",
			update: saveBoardOrder
		});
	}

	function saveBoardOrder() {
		const order = $("#admin-notes-board .admin-note-card").map(function () {
			return $(this).data("note-id");
		}).get();

		postAjax({
			action: "admin_notes_save_order",
			nonce: AdminNotes.nonce,
			order: JSON.stringify(order)
		});
	}

	// -----------------------------
	// CHECKLIST SORTABLE
	// -----------------------------
	function refreshChecklistSortable($list) {
		$list.sortable({
			handle: ".check-drag",
			placeholder: "check-placeholder",
			update: function () {
				saveChecklist($list.closest(".admin-note-card"));
			}
		});
	}

	// -----------------------------
	// INIT
	// -----------------------------
	$(".admin-note-card").each(function () {
		bindCard($(this));
	});

	refreshBoardSortable();

});
