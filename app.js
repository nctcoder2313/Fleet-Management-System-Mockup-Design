const vehicleRows = [...document.querySelectorAll("#vehicle-rows tr")];
const tripItems = [...document.querySelectorAll(".trip-item")];
const filterTabs = [...document.querySelectorAll("[data-table-filter]")];
const searchInput = document.querySelector("#global-search");
const emptyState = document.querySelector("#fleet-empty");
const tripsEmptyState = document.querySelector("#trips-empty");
const tableCount = document.querySelector("#table-count");
const toast = document.querySelector("#toast");
const detailDialog = document.querySelector("#detail-dialog");
const issueDialog = document.querySelector("#issue-dialog");
let activeFilter = "all";
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
    item.hidden = Boolean(query) && !item.dataset.search.includes(query);
  });
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
        description: data.get("description")
      })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Issue report failed.");
    showToast(result.message);
    form.reset();
    issueDialog.close();
  } catch (error) {
    showToast(error.message || "Issue report failed. Please try again.");
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

const todayLabel = document.querySelector("#today-label");
if (todayLabel) {
  todayLabel.textContent = new Intl.DateTimeFormat("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric"
  }).format(new Date());
}
