const vehicleRows = [...document.querySelectorAll("#vehicle-rows tr")];
const vehicleCards = [...document.querySelectorAll("[data-vehicle-card]")];
const vehicleFilterButtons = [...document.querySelectorAll("[data-vehicle-filter]")];
const tripItems = [...document.querySelectorAll(".trip-item")];
const driverRows = [...document.querySelectorAll("[data-driver-row]")];
const filterTabs = [...document.querySelectorAll("[data-table-filter]")];
const searchInput = document.querySelector("#global-search");
const emptyState = document.querySelector("#fleet-empty");
const tripsEmptyState = document.querySelector("#trips-empty");
const driverEmptyState = document.querySelector("#driver-empty");
const driverVisibleCount = document.querySelector("#driver-visible-count");
const fleetVehicleEmpty = document.querySelector("#fleet-vehicle-empty");
const fleetVehicleVisibleCount = document.querySelector("#fleet-vehicle-visible-count");
const tableCount = document.querySelector("#table-count");
const toast = document.querySelector("#toast");
const detailDialog = document.querySelector("#detail-dialog");
const issueDialog = document.querySelector("#issue-dialog");
const issueDetailDialog = document.querySelector("#issue-detail-dialog");
const driverDetailDialog = document.querySelector("#driver-detail-dialog");
let activeFilter = "all";
let activeVehicleFilter = "all";
let toastTimeout;

function updateFleet() {
  const query = searchInput?.value.trim().toLowerCase() || "";
  let visibleCount = 0;

  vehicleRows.forEach((row) => {
    const matchesFilter = activeFilter === "all" ||
      (activeFilter === "drivers" ? row.querySelector(".driver-cell") !== null : row.dataset.status === activeFilter);
    const matchesSearch = !query || row.dataset.search.includes(query);
    const visible = matchesFilter && matchesSearch;
    row.hidden = !visible;
    if (visible) visibleCount += 1;
  });

  let visibleTrips = 0;
  tripItems.forEach((trip) => {
    const matchesSearch = !query || trip.dataset.search.includes(query);
    trip.hidden = !matchesSearch;
    if (matchesSearch) visibleTrips += 1;
  });

  if (emptyState) emptyState.hidden = visibleCount > 0;
  if (tripsEmptyState) tripsEmptyState.hidden = visibleTrips > 0;
  if (tableCount) tableCount.textContent = `Showing ${visibleCount} of ${tableCount.dataset.total} vehicles`;
  document.querySelectorAll(".search-item").forEach((item) => {
    if (!item.matches("[data-vehicle-card]")) {
      item.hidden = Boolean(query) && !item.dataset.search.includes(query);
    }
  });
  let visibleVehicles = 0;
  vehicleCards.forEach((card) => {
    const matchesStatus = activeVehicleFilter === "all" ||
      (activeVehicleFilter === "unassigned"
        ? card.dataset.driverAssigned === "0"
        : card.dataset.vehicleStatus === activeVehicleFilter);
    const matchesSearch = !query || card.dataset.search.includes(query);
    card.hidden = !matchesStatus || !matchesSearch;
    if (!card.hidden) visibleVehicles += 1;
  });
  if (fleetVehicleEmpty) fleetVehicleEmpty.hidden = visibleVehicles > 0;
  if (fleetVehicleVisibleCount) fleetVehicleVisibleCount.textContent = String(visibleVehicles);
  if (driverRows.length > 0) {
    const visibleDrivers = driverRows.filter((row) => !row.hidden).length;
    if (driverVisibleCount) driverVisibleCount.textContent = String(visibleDrivers);
    if (driverEmptyState) driverEmptyState.hidden = visibleDrivers > 0;
  }
}

function setFleetFilter(filter) {
  activeFilter = filter;
  filterTabs.forEach((tab) => tab.classList.toggle("active", tab.dataset.tableFilter === filter));
  updateFleet();
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("visible");
  window.clearTimeout(toastTimeout);
  toastTimeout = window.setTimeout(() => toast.classList.remove("visible"), 2800);
}

function openDetails(value) {
  const [title, ...details] = value.split("|");
  document.querySelector("#dialog-title").textContent = title;
  document.querySelector("#dialog-body").replaceChildren(
    ...details.map((detail) => {
      const line = document.createElement("div");
      line.textContent = detail;
      return line;
    })
  );
  detailDialog.showModal();
}

filterTabs.forEach((tab) => tab.addEventListener("click", () => setFleetFilter(tab.dataset.tableFilter)));
vehicleFilterButtons.forEach((button) => {
  button.addEventListener("click", () => {
    activeVehicleFilter = button.dataset.vehicleFilter;
    vehicleFilterButtons.forEach((filterButton) => {
      const selected = filterButton === button;
      filterButton.classList.toggle("active", selected);
      filterButton.setAttribute("aria-pressed", String(selected));
    });
    updateFleet();
  });
});
searchInput?.addEventListener("input", updateFleet);
document.querySelectorAll("[data-detail]").forEach((button) => {
  button.addEventListener("click", () => openDetails(button.dataset.detail));
});
document.querySelectorAll("[data-toast]").forEach((button) => {
  button.addEventListener("click", () => showToast(button.dataset.toast));
});
document.querySelectorAll("[data-maintenance-request]").forEach((button) => {
  button.addEventListener("click", async () => {
    button.disabled = true;
    try {
      const response = await fetch("api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "request-maintenance", id: button.dataset.maintenanceRequest })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Maintenance request failed.");
      button.textContent = "Requested";
      const statusTag = button.closest("tr").querySelector(".maintenance-tag");
      statusTag.textContent = "Requested";
      statusTag.classList.remove("tag-overdue", "tag-soon");
      statusTag.classList.add("tag-requested");
      showToast(result.message);
    } catch (error) {
      button.disabled = false;
      showToast(error.message || "Maintenance request failed. Please try again.");
    }
  });
});
document.querySelector("#report-issue")?.addEventListener("click", () => issueDialog.showModal());

const issueStatusLabels = {
  awaiting_review: "Awaiting Review",
  in_progress: "In Progress",
  delayed: "Delayed",
  completed: "Completed"
};
const issueTransitions = {
  awaiting_review: ["awaiting_review", "in_progress"],
  in_progress: ["in_progress", "delayed", "completed"],
  delayed: ["delayed", "in_progress", "completed"],
  completed: ["completed"]
};
document.querySelectorAll(".issue-card").forEach((card) => {
  card.addEventListener("click", () => {
    const data = card.dataset;
    const form = document.querySelector("#issue-detail-form");
    const setValue = (selector, value) => {
      document.querySelector(selector).textContent = value || "Not specified";
    };
    document.querySelector("#issue-detail-title").textContent = `Issue #${data.issueId}`;
    setValue("#issue-detail-vehicle", data.issueVehicle);
    setValue("#issue-detail-type", data.issueType);
    setValue("#issue-detail-priority", data.issuePriority);
    setValue("#issue-detail-created", data.issueCreated);
    setValue("#issue-detail-description", data.issueDescription);
    form.elements.id.value = data.issueId;
    form.elements.due_date.value = data.issueDue;
    form.elements.maintenance_notes.value = data.issueNotes;

    const statusSelect = form.elements.status;
    statusSelect.replaceChildren(...(issueTransitions[data.issueStatus] || [data.issueStatus]).map((status) => {
      const option = document.createElement("option");
      option.value = status;
      option.textContent = issueStatusLabels[status] || status;
      return option;
    }));
    statusSelect.value = data.issueStatus;
    issueDetailDialog.showModal();
  });
});

document.querySelectorAll("[data-edit-driver]").forEach((button) => {
  button.addEventListener("click", () => {
    const driver = button.closest("[data-driver-row]").dataset;
    const form = document.querySelector("#driver-detail-form");
    document.querySelector("#driver-detail-title").textContent = driver.driverName;
    form.elements.id.value = driver.driverId;
    form.elements.on_duty.value = driver.driverDuty;
    form.elements.vehicle_code.value = driver.driverVehicle;
    driverDetailDialog.showModal();
  });
});

document.querySelectorAll(".dialog-close").forEach((button) => {
  button.addEventListener("click", () => button.closest("dialog").close());
});
document.querySelectorAll(".dialog-done").forEach((button) => {
  if (button.type !== "submit") button.addEventListener("click", () => button.closest("dialog").close());
});
document.querySelectorAll("dialog").forEach((dialog) => {
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
});
document.querySelector("#issue-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitButton = form.querySelector('[type="submit"]');
  submitButton.disabled = true;
  const data = new FormData(form);

  try {
    const response = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "report-issue",
        vehicle: data.get("vehicle"),
        description: data.get("description"),
        issue_type: data.get("issue_type"),
        priority: data.get("priority")
      })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Issue report failed.");
    showToast(result.message);
    form.reset();
    issueDialog.close();
    window.setTimeout(() => window.location.reload(), 650);
  } catch (error) {
    showToast(error.message || "Issue report failed. Please try again.");
  } finally {
    submitButton.disabled = false;
  }
});

document.querySelector("#issue-detail-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitButton = form.querySelector('[type="submit"]');
  submitButton.disabled = true;
  const data = new FormData(form);

  try {
    const response = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "update-issue",
        id: data.get("id"),
        status: data.get("status"),
        due_date: data.get("due_date"),
        maintenance_notes: data.get("maintenance_notes")
      })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Issue update failed.");
    showToast(result.message);
    issueDetailDialog.close();
    window.setTimeout(() => window.location.reload(), 650);
  } catch (error) {
    showToast(error.message || "Issue update failed. Please try again.");
  } finally {
    submitButton.disabled = false;
  }
});

document.querySelector("#driver-detail-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitButton = form.querySelector('[type="submit"]');
  submitButton.disabled = true;
  const data = new FormData(form);

  try {
    const response = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "update-driver",
        id: data.get("id"),
        on_duty: data.get("on_duty"),
        vehicle_code: data.get("vehicle_code")
      })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Driver update failed.");
    showToast(result.message);
    driverDetailDialog.close();
    window.setTimeout(() => window.location.reload(), 650);
  } catch (error) {
    showToast(error.message || "Driver update failed. Please try again.");
  } finally {
    submitButton.disabled = false;
  }
});

document.querySelectorAll("[data-record-form]").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('[type="submit"]');
    submitButton.disabled = true;
    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const response = await fetch("api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "The record could not be saved.");
      showToast(result.message);
      window.setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      submitButton.disabled = false;
      showToast(error.message || "The record could not be saved. Please try again.");
    }
  });
});

document.addEventListener("keydown", (event) => {
  if (searchInput && (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
    event.preventDefault();
    searchInput.focus();
  }
});

const datePicker = document.querySelector("#date-picker");
if (datePicker) {
  const dateSelector = datePicker.querySelector("#date-selector");
  const popover = datePicker.querySelector("#date-picker-popover");
  const monthLabel = datePicker.querySelector("#calendar-month-label");
  const calendarDays = datePicker.querySelector("#calendar-days");
  const selectionHint = datePicker.querySelector("#calendar-selection-hint");
  const applyButton = datePicker.querySelector("#calendar-apply");
  const utcDate = (year, month, day) => new Date(Date.UTC(year, month, day));
  const dateKey = (date) => [
    date.getUTCFullYear(),
    String(date.getUTCMonth() + 1).padStart(2, "0"),
    String(date.getUTCDate()).padStart(2, "0")
  ].join("-");
  const parseDateKey = (value) => {
    const [year, month, day] = value.split("-").map(Number);
    return utcDate(year, month - 1, day);
  };
  const formatDate = (value) => new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    timeZone: "UTC"
  }).format(parseDateKey(value));
  let appliedStart = dateSelector.dataset.start;
  let appliedEnd = dateSelector.dataset.end;
  let draftStart = appliedStart;
  let draftEnd = appliedEnd;
  let rangeAnchor = null;
  let visibleMonth = parseDateKey(appliedStart);
  visibleMonth = utcDate(visibleMonth.getUTCFullYear(), visibleMonth.getUTCMonth(), 1);

  function renderCalendar() {
    monthLabel.textContent = new Intl.DateTimeFormat("en-US", {
      month: "long",
      year: "numeric",
      timeZone: "UTC"
    }).format(visibleMonth);
    calendarDays.replaceChildren();

    const year = visibleMonth.getUTCFullYear();
    const month = visibleMonth.getUTCMonth();
    const firstWeekday = (utcDate(year, month, 1).getUTCDay() + 6) % 7;
    const daysInMonth = utcDate(year, month + 1, 0).getUTCDate();
    for (let blank = 0; blank < firstWeekday; blank += 1) {
      const spacer = document.createElement("span");
      spacer.setAttribute("aria-hidden", "true");
      calendarDays.append(spacer);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const key = dateKey(utcDate(year, month, day));
      const button = document.createElement("button");
      button.type = "button";
      button.textContent = String(day);
      button.dataset.date = key;
      button.setAttribute("aria-label", formatDate(key));
      button.setAttribute("aria-pressed", String(key === draftStart || key === draftEnd));
      if (key === draftStart || key === draftEnd) button.classList.add("is-range-edge");
      if (key > draftStart && key < draftEnd) button.classList.add("is-in-range");
      button.addEventListener("click", () => {
        // The first click anchors a draft range; the second completes it.
        if (rangeAnchor === null) {
          rangeAnchor = key;
          draftStart = key;
          draftEnd = key;
        } else {
          draftStart = key < rangeAnchor ? key : rangeAnchor;
          draftEnd = key < rangeAnchor ? rangeAnchor : key;
          rangeAnchor = null;
        }
        updateSelectionHint();
        renderCalendar();
      });
      calendarDays.append(button);
    }
    updateSelectionHint();
  }

  function updateSelectionHint() {
    selectionHint.textContent = rangeAnchor
      ? `Choose an end date. Start: ${formatDate(rangeAnchor)}`
      : draftStart === draftEnd
        ? `Selected: ${formatDate(draftStart)}`
        : `Selected: ${formatDate(draftStart)} – ${formatDate(draftEnd)}`;
    applyButton.disabled = rangeAnchor !== null;
  }

  function setDraftRange(start, end) {
    draftStart = start;
    draftEnd = end;
    rangeAnchor = null;
    visibleMonth = parseDateKey(start);
    visibleMonth = utcDate(visibleMonth.getUTCFullYear(), visibleMonth.getUTCMonth(), 1);
    renderCalendar();
  }

  function closePicker(returnFocus = false) {
    popover.hidden = true;
    dateSelector.setAttribute("aria-expanded", "false");
    rangeAnchor = null;
    draftStart = appliedStart;
    draftEnd = appliedEnd;
    if (returnFocus) dateSelector.focus();
  }

  dateSelector.addEventListener("click", () => {
    if (!popover.hidden) {
      closePicker();
      return;
    }
    draftStart = appliedStart;
    draftEnd = appliedEnd;
    rangeAnchor = null;
    visibleMonth = parseDateKey(appliedStart);
    visibleMonth = utcDate(visibleMonth.getUTCFullYear(), visibleMonth.getUTCMonth(), 1);
    renderCalendar();
    popover.hidden = false;
    dateSelector.setAttribute("aria-expanded", "true");
  });

  datePicker.querySelector("#calendar-previous").addEventListener("click", () => {
    visibleMonth = utcDate(visibleMonth.getUTCFullYear(), visibleMonth.getUTCMonth() - 1, 1);
    renderCalendar();
  });
  datePicker.querySelector("#calendar-next").addEventListener("click", () => {
    visibleMonth = utcDate(visibleMonth.getUTCFullYear(), visibleMonth.getUTCMonth() + 1, 1);
    renderCalendar();
  });
  datePicker.querySelectorAll("[data-date-preset]").forEach((button) => {
    button.addEventListener("click", () => {
      const today = utcDate(
        new Date().getUTCFullYear(),
        new Date().getUTCMonth(),
        new Date().getUTCDate()
      );
      const todayKey = dateKey(today);
      if (button.dataset.datePreset === "today") {
        setDraftRange(todayKey, todayKey);
      } else if (button.dataset.datePreset === "last-7-days") {
        setDraftRange(dateKey(utcDate(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate() - 6)), todayKey);
      } else {
        setDraftRange(dateKey(utcDate(today.getUTCFullYear(), today.getUTCMonth(), 1)), todayKey);
      }
    });
  });
  datePicker.querySelector("#calendar-cancel").addEventListener("click", () => closePicker(true));
  applyButton.addEventListener("click", () => {
    if (rangeAnchor !== null || !draftStart || !draftEnd) return;
    // Applying the selection reloads Overview with validated start/end query parameters.
    const url = new URL(window.location.href);
    url.searchParams.set("view", "overview");
    url.searchParams.set("start", draftStart);
    url.searchParams.set("end", draftEnd);
    window.location.assign(url);
  });
  document.addEventListener("click", (event) => {
    if (!datePicker.contains(event.target) && !popover.hidden) closePicker();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !popover.hidden) closePicker(true);
  });
}
