const toast = document.querySelector("#toast");
const expenseDialog = document.querySelector("#expense-dialog");
const advanceDialog = document.querySelector("#advance-dialog");
const liquidationDialog = document.querySelector("#liquidation-dialog");
const billDialog = document.querySelector("#bill-dialog");
const billPaymentDialog = document.querySelector("#bill-payment-dialog");
let toastTimeout;

function showToast(message) {
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add("visible");
  window.clearTimeout(toastTimeout);
  toastTimeout = window.setTimeout(() => toast.classList.remove("visible"), 2800);
}

document.querySelector("#open-expense-dialog")?.addEventListener("click", () => {
  const form = document.querySelector("#expense-form");
  form.reset();
  form.elements.action.value = "record-expense";
  form.elements.id.value = "";
  document.querySelector("#expense-dialog").querySelector("h2").textContent = "Record fleet expense";
  form.querySelector('[type="submit"]').lastChild.textContent = " Save expense";
  updateAssignedDriver();
  expenseDialog?.showModal();
});
document.querySelector("#open-advance-dialog")?.addEventListener("click", () => advanceDialog?.showModal());
document.querySelector("#open-bill-dialog")?.addEventListener("click", () => {
  const form = document.querySelector("#bill-form");
  form.reset();
  form.elements.action.value = "record-bill";
  form.elements.id.value = "";
  form.elements.amount.readOnly = false;
  document.querySelector("#bill-dialog-title").textContent = "Record supplier bill";
  form.querySelector('[type="submit"]').textContent = "Save bill";
  billDialog?.showModal();
});

document.querySelectorAll("[data-edit-expense]").forEach((button) => {
  button.addEventListener("click", () => {
    const form = document.querySelector("#expense-form");
    form.elements.action.value = "update-expense";
    form.elements.id.value = button.dataset.editExpense;
    form.elements.expense_date.value = button.dataset.date;
    form.elements.vehicle_code.value = button.dataset.vehicle;
    form.elements.category.value = button.dataset.category;
    form.elements.status.value = button.dataset.status;
    form.elements.description.value = button.dataset.description;
    form.elements.amount.value = button.dataset.amount;
    form.elements.recorded_by.value = button.dataset.recordedBy;
    document.querySelector("#expense-dialog").querySelector("h2").textContent = "Edit expense";
    form.querySelector('[type="submit"]').lastChild.textContent = " Save changes";
    expenseDialog?.showModal();
  });
});

document.querySelectorAll("[data-expense-detail]").forEach((button) => {
  button.addEventListener("click", () => {
    const values = {
      id: `EXP-${String(button.dataset.id).padStart(5, "0")}`,
      date: button.dataset.date,
      vehicle: button.dataset.vehicle,
      category: button.dataset.category,
      description: button.dataset.description,
      amount: `₱${Number(button.dataset.amount).toLocaleString("en-PH", { minimumFractionDigits: 2 })}`,
      recorder: button.dataset.recordedBy,
      status: button.dataset.status
    };
    for (const [field, value] of Object.entries(values)) {
      document.querySelector(`#expense-detail-${field}`).textContent = value;
    }
    const receiptLink = document.querySelector("#expense-detail-receipt");
    receiptLink.hidden = button.dataset.hasReceipt !== "1";
    receiptLink.href = `?attachment=expense&id=${encodeURIComponent(button.dataset.id)}`;
    document.querySelector("#expense-details-dialog").showModal();
  });
});

document.querySelectorAll("[data-edit-bill]").forEach((button) => {
  button.addEventListener("click", () => {
    const form = document.querySelector("#bill-form");
    form.elements.action.value = "update-bill";
    form.elements.id.value = button.dataset.editBill;
    form.elements.supplier.value = button.dataset.supplier;
    form.elements.description.value = button.dataset.description;
    form.elements.category.value = button.dataset.category;
    form.elements.vehicle_code.value = button.dataset.vehicle;
    form.elements.bill_date.value = button.dataset.billDate;
    form.elements.due_date.value = button.dataset.dueDate;
    form.elements.amount.value = button.dataset.amount;
    form.elements.amount.readOnly = button.dataset.hasPayments === "1";
    document.querySelector("#bill-dialog-title").textContent = "Edit supplier bill";
    form.querySelector('[type="submit"]').textContent = "Save changes";
    billDialog?.showModal();
  });
});

document.querySelectorAll("[data-pay-bill]").forEach((button) => {
  button.addEventListener("click", () => {
    const form = document.querySelector("#bill-payment-form");
    form.reset();
    form.elements.id.value = button.dataset.payBill;
    form.elements.amount.max = button.dataset.balance;
    form.elements.amount.value = button.dataset.balance;
    document.querySelector("#bill-payment-supplier").textContent = button.dataset.supplier;
    document.querySelector("#bill-payment-balance").textContent = `₱${Number(button.dataset.balance).toLocaleString("en-PH", { minimumFractionDigits: 2 })}`;
    billPaymentDialog?.showModal();
  });
});

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

document.querySelectorAll("[data-review-liquidation]").forEach((button) => {
  button.addEventListener("click", async () => {
    const decision = button.dataset.review;
    if (!window.confirm(`Are you sure you want to ${decision} this liquidation?`)) return;
    button.disabled = true;
    try {
      const response = await fetch("api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "review-liquidation", id: button.dataset.reviewLiquidation, decision })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Liquidation review failed.");
      showToast(result.message);
      window.setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      button.disabled = false;
      showToast(error.message || "Liquidation review failed. Please try again.");
    }
  });
});

document.querySelectorAll("[data-print]").forEach((button) => {
  button.addEventListener("click", () => window.print());
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
    const payload = new FormData(form);

    try {
      const response = await fetch("api.php", {
        method: "POST",
        body: payload
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "The fleet record could not be saved.");
      showToast(result.message);
      window.setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      submitButton.disabled = false;
      showToast(error.message || "The fleet record could not be saved. Please try again.");
    }
  });
});
