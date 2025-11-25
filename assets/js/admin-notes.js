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

	// -----------------------------------------------------------
	// ADD NEW NOTE
	// -----------------------------------------------------------
	$("#admin-notes-add").on("click", function () {
		$btn = $(this);
		$btn.prop("disabled",true);
		// Background AJAX — WordPress
		postAjax({
			action: "admin_notes_add",
			nonce: AdminNotes.nonce
		}, function (res) {

			// if fails → show error
			if (!res || !res.success) {
				alert("Error: Could not create note");
				return;
			}

			// Render a new note if post created successfully
			const $newCard = createNoteCard(res.data);
			$("#admin-notes-board").append($newCard);
			bindCard($newCard);
			refreshBoardSortable();


			// Enable button & page scroll to bottom 
			$btn.prop("disabled",false);

			$("html, body").animate({
			scrollTop: $(document).height()
			}, 1000); 
		});
		

	});

	// CREATE NOTE CARD — supports temp ID + real ID
	function createNoteCard(data) {
		return $(`
			<div class="admin-note-card" data-note-id="${data.id}"
				style="background: color-mix(in srgb, ${data.color} 55%, white 45%);">

				<header class="admin-note-header"
					style="background:${data.color};
						border-top:4px solid color-mix(in srgb, ${data.color} 80%, black 20%);">

					<span class="admin-note-drag-handle">::</span>

					<input class="admin-note-title" value="${data.title}" />

					<div class="admin-note-actions">
						<button class="admin-note-minimize">▼</button>
						<button class="admin-note-delete">🗑</button>
					</div>
				</header>

				<div class="admin-note-body">

					<ul class="admin-note-checklist" data-note-id="${data.id}"></ul>

					<div class="admin-note-add">
						<input type="text" class="admin-note-add-input"
							placeholder="+ Add a task and press Enter" />
					</div>

					${noteFooterHTML(data)}
				</div>
			</div>
		`);
	}

	// FOOTER HTML
	function noteFooterHTML(data) {
		const presets = [
			'#bae6fd', '#d9f99d', '#bbf7d0', '#c7d2fe', '#e9d5ff',
			'#fbcfe8', '#ffd9d9', '#fed7aa', '#fef08a'
		];

		const presetButtons = presets.map(c => {
			return `
				<button class="admin-note-color-swatch"
					data-color="${c}"
					data-note-id="${data.id}"
					title="${c}"
					style="background:${c}">
				</button>
			`;
		}).join('');

		return `
			<div class="admin-note-footer">

				<div class="admin-note-colors">
					${presetButtons}
					<input type="color" class="admin-note-color-picker" data-note-id="${data.id}" />
				</div>

				<div class="admin-note-visibility">
					<select class="admin-note-visibility-select" data-note-id="${data.id}">
						<option value="only_me">🔒 Only Me</option>
						<option value="all_admins">👁️ All Admins</option>
						<option value="editors_and_above">👨‍👨‍👦 Editors & above</option>
					</select>
				</div>

				<div class="total-items">
					<p>${data.task_count ?? 0} Tasks</p>
				</div>
			</div>
		`;
	}



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

			// instantly hide from frontend
			$(this).closest('.admin-note-card').hide();

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
					<span class="check-drag">☰</span>
					<label>
						<input type="checkbox" class="check-toggle">
						<span class="check-text" contenteditable="true">${txt}</span>
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

			$card_header = $card.children(".admin-note-header");
			$card_header.css("background", color);
			$card_header.css("borderTop",`4px solid color-mix(in srgb, ${color} 90%, black 10% `);
			$card.css("background", `color-mix(in srgb, ${color} 55%, white 45%)`);


			postAjax({
				action: "admin_notes_save_color",
				note_id: noteID,
				color,
				nonce: AdminNotes.nonce
			});
		});

		$card.find(".admin-note-color-picker").on("input", function () {
			const color = $(this).val();
			$card_header = $card.children(".admin-note-header");
			$card_header.css("background", color);
			$card_header.css("borderTop",`4px solid color-mix(in srgb, ${color} 90%, black 10% `);
			$card.css("background", `color-mix(in srgb, ${color} 55%, white 45%)`);

			postAjax({
				action: "admin_notes_save_color",
				note_id: noteID,
				color,
				nonce: AdminNotes.nonce
			});
		});


		// -------------------------------
		// Visibility update on change 
		// -------------------------------
		$card.find(".admin-note-visibility-select").on('change',function() {
			const visibility = $(this).val();

			postAjax({
				action: "admin_notes_save_visibility",
				note_id: noteID,
				visibility,
				nonce: AdminNotes.nonce
			});
		})

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


		/** inline edit - USE DBLCLICK */
		$text.on("dblclick", function (e) {
			e.stopPropagation(); 

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
    const order = $("#admin-notes-board .admin-note-card")
        .map(function () {
            const id = $(this).data("note-id");
            return id;
        })
        .get()
        .filter(Boolean); // remove nulls


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
