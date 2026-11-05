const toast = document.querySelector("#toast");
const expenseDialog = document.querySelector("#expense-dialog");
const advanceDialog = document.querySelector("#advance-dialog");
const liquidationDialog = document.querySelector("#liquidation-dialog");
let toastTimeout;

function showToast(message) {
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add("visible");
  window.clearTimeout(toastTimeout);
  toastTimeout = window.setTimeout(() => toast.classList.remove("visible"), 2800);
}

document.querySelector("#open-expense-dialog")?.addEventListener("click", () => expenseDialog?.showModal());
document.querySelector("#open-advance-dialog")?.addEventListener("click", () => advanceDialog?.showModal());

const expenseVehicle = document.querySelector("#expense-vehicle");
const assignedDriverLabel = document.querySelector("#expense-assigned-driver");
function updateAssignedDriver() {
  const selectedVehicle = expenseVehicle?.selectedOptions[0];
  if (assignedDriverLabel) assignedDriverLabel.textContent = selectedVehicle?.dataset.driver || "Unassigned";
}
expenseVehicle?.addEventListener("change", updateAssignedDriver);
updateAssignedDriver();

document.querySelectorAll("[data-liquidate]").forEach((button) => {
  button.addEventListener("click", () => {
    const form = document.querySelector("#liquidation-form");
    const amount = form.elements.amount;
    form.elements.id.value = button.dataset.liquidate;
    form.elements.amount.value = button.dataset.balance;
    amount.max = button.dataset.balance;
    document.querySelector("#liquidation-driver").textContent = button.dataset.driver;
    liquidationDialog?.showModal();
  });
});
const accountingPeriod = document.querySelector("#accounting-period");
accountingPeriod?.addEventListener("change", () => {
  document.querySelectorAll(".custom-date-field").forEach((field) => {
    field.hidden = accountingPeriod.value !== "custom";
  });
});
document.querySelector("#report-year")?.addEventListener("change", () => {
  if (!accountingPeriod) return;
  accountingPeriod.value = "year";
  document.querySelectorAll(".custom-date-field").forEach((field) => {
    field.hidden = true;
  });
});
document.querySelectorAll("dialog").forEach((dialog) => {
  dialog.querySelector(".dialog-close")?.addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
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
      if (!response.ok) throw new Error(result.error || "The accounting record could not be saved.");
      showToast(result.message);
      window.setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      submitButton.disabled = false;
      showToast(error.message || "The accounting record could not be saved. Please try again.");
    }
  });
});
