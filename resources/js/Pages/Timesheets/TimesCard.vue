<script setup>
import { computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Calendar from "primevue/calendar";
import Dropdown from "primevue/dropdown";
// Imports pour les notifications
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";

const props = defineProps({
  data: Object, // Reçoit les infos de l'employé, les dates et le mode (simple ou bulk)
});

const emit = defineEmits(["close"]);

/**
 * INITIALISATION DES SERVICES
 */
const toast = useToast(); // Initialisation indispensable pour utiliser toast.add()

/**
 * LOGIQUE DE VERROUILLAGE
 * Détermine si le formulaire doit être désactivé selon le rôle et le statut.
 */
const isLocked = computed(() => {
  // Bloque si c'est un Téléconseiller ou si la feuille est déjà soumise
  if (props.data.role === "tc") return true;
  if (props.data.status === "soumis") return true;
  return false;
});

/**
 * GÉNÉRATION DES OPTIONS DE DATE
 * Prépare la liste déroulante pour le mode de saisie groupée.
 */
const dateOptions = computed(() => {
  if (!props.data.isBulk || !props.data.all_dates) return [];
  return props.data.all_dates.map((d) => ({
    // Formate la date en texte lisible (ex: "lundi 20 mai")
    label: new Intl.DateTimeFormat("fr-FR", {
      weekday: "long",
      day: "numeric",
      month: "long",
    }).format(new Date(d)),
    value: d,
  }));
});

/**
 * INITIALISATION DU FORMULAIRE
 * Prépare l'objet de données réactif pour Inertia.
 */
const form = useForm({
  timesheet_id: props.data.entry?.timesheet_id || props.data.timesheet_id,
  timesheet_ids: props.data.isBulk ? props.data.timesheet_ids : null,
  date: props.data.date,
  // Conversion des chaînes HH:mm en objets Date pour PrimeVue
  check_in: props.data.entry?.check_in
    ? new Date(`2000-01-01 ${props.data.entry.check_in}`)
    : null,
  check_out: props.data.entry?.check_out
    ? new Date(`2000-01-01 ${props.data.entry.check_out}`)
    : null,
  break_duration: props.data.entry?.break_duration || 0,
  comment: props.data.entry?.comment || "",
});

/**
 * FORMATAGE DE L'HEURE
 * Convertit un objet Date en chaîne "HH:mm" pour le backend.
 */
const formatToHHmm = (date) => {
  if (!date) return null;
  const d = new Date(date);
  return (
    d.getHours().toString().padStart(2, "0") +
    ":" +
    d.getMinutes().toString().padStart(2, "0")
  );
};

/**
 * VALIDATION HORAIRE AVANT SOUMISSION
 */
const validateFormSubmission = () => {
  const checkIn = formatToHHmm(form.check_in);
  const checkOut = formatToHHmm(form.check_out);

  // 1. Présence des heures
  if (!checkIn || !checkOut) {
    toast.add({
      severity: "error",
      summary: "Erreur de validation",
      detail: "Les heures d'arrivée et de départ sont requises",
      life: 5000,
    });
    return false;
  }

  const [inHours, inMinutes] = checkIn.split(":").map(Number);
  const [outHours, outMinutes] = checkOut.split(":").map(Number);

  // 2. Format valide
  if (inHours > 23 || outHours > 23 || inMinutes > 59 || outMinutes > 59) {
    toast.add({
      severity: "error",
      summary: "Format horaire invalide",
      detail: "Heures (00-23) et minutes (00-59) uniquement",
      life: 5000,
    });
    return false;
  }

  const inTotalMinutes = inHours * 60 + inMinutes;
  const outTotalMinutes = outHours * 60 + outMinutes;

  // 3. Chronologie
  if (inTotalMinutes >= outTotalMinutes) {
    toast.add({
      severity: "error",
      summary: "Horaires incohérents",
      detail: "L'arrivée doit être avant le départ",
      life: 5000,
    });
    return false;
  }

  // 4. Calcul durée nette (travail - pause)
  let workMinutes = outTotalMinutes - inTotalMinutes;
  if (form.break_duration) {
    workMinutes -= parseInt(form.break_duration);
  }

  if (workMinutes <= 0) {
    toast.add({
      severity: "error",
      summary: "Durée invalide",
      detail: "La durée de travail doit être positive après déduction de la pause",
      life: 5000,
    });
    return false;
  }

  return true;
};

/**
 * SOUMISSION DU FORMULAIRE
 */
const submit = () => {
  if (isLocked.value) return;

  if (!validateFormSubmission()) return;

  form
    .transform((data) => ({
      ...data,
      timesheet_ids: props.data.isBulk
        ? props.data.timesheet_ids
        : [props.data.timesheet_id],
      check_in: formatToHHmm(data.check_in),
      check_out: formatToHHmm(data.check_out),
    }))
    .post("/timesheet-entries", {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["calendar"] });
        emit("close");
      },
    });
};

/**
 * LOGIQUE DE SAISIE ASSISTÉE (HH:MM)
 */
const formatTimeInput = (input) => {
  const numbers = input.replace(/\D/g, "");
  const limited = numbers.slice(0, 4);
  if (limited.length <= 2) return limited;
  return limited.slice(0, 2) + ":" + limited.slice(2);
};

const isValidTime = (timeString) => {
  if (!timeString || !timeString.includes(":")) return false;
  const [hours, minutes] = timeString.split(":");
  const h = parseInt(hours, 10);
  const m = parseInt(minutes, 10);
  return h >= 0 && h <= 23 && m >= 0 && m <= 59;
};

const convertToTimeDate = (timeString) => {
  if (!timeString || !isValidTime(timeString)) return null;
  const [hours, minutes] = timeString.split(":");
  const date = new Date();
  date.setHours(parseInt(hours, 10), parseInt(minutes, 10), 0, 0);
  return date;
};

const handleCheckInInput = (event) => {
  const input = event.target;
  const cursorPosition = input.selectionStart;
  const formatted = formatTimeInput(input.value);

  if (isValidTime(formatted)) {
    const timeDate = convertToTimeDate(formatted);
    if (timeDate) form.check_in = timeDate;
  }

  setTimeout(() => {
    const newPos = Math.min(cursorPosition, formatted.length);
    input.setSelectionRange(newPos, newPos);
  }, 0);
};

const handleCheckOutInput = (event) => {
  const input = event.target;
  const cursorPosition = input.selectionStart;
  const formatted = formatTimeInput(input.value);

  if (isValidTime(formatted)) {
    const timeDate = convertToTimeDate(formatted);
    if (timeDate) form.check_out = timeDate;
  }

  setTimeout(() => {
    const newPos = Math.min(cursorPosition, formatted.length);
    input.setSelectionRange(newPos, newPos);
  }, 0);
};
</script>

<template>
  <!-- Le composant Toast doit être présent pour afficher les erreurs de validation -->
  <Toast />

  <div class="p-fluid grid gap-4">
    <!-- Bandeau d'information contextuel -->
    <div class="mb-1 p-3 rounded bg-blue-50 border border-blue-100 shadow-sm">
      <div class="font-black text-blue-900 text-sm uppercase">
        {{ data.employee_name }}
      </div>
      <div class="text-[10px] text-blue-600 font-bold uppercase" v-if="!data.isBulk">
        Date : {{ data.date }}
      </div>
      <div class="text-[10px] text-orange-600 font-bold uppercase" v-else>
        Saisie Multiple
      </div>
    </div>

    <!-- SÉLECTEUR DE DATE (Mode Multiple uniquement) -->
    <div v-if="data.isBulk" class="field">
      <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block"
        >1. Choisir le jour à remplir</label
      >
      <Dropdown
        v-model="form.date"
        :options="dateOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Sélectionner un jour"
        class="w-full font-bold border-blue-300"
      />
    </div>

    <!-- SAISIE DES HORAIRES -->
    <div class="field">
      <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block">
        {{ data.isBulk ? "2. Heure Arrivée" : "Heure Arrivée" }}
      </label>
      <Calendar
        v-model="form.check_in"
        timeOnly
        hourFormat="24"
        :disabled="isLocked"
        placeholder="--:--"
        @input="handleCheckInInput"
        @keydown="(e) => { if (!/[0-9]|Backspace|Delete|Tab|Enter/.test(e.key)) e.preventDefault(); }"
      />
    </div>

    <div class="field">
      <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block"
        >Heure Départ</label
      >
      <Calendar
        v-model="form.check_out"
        timeOnly
        hourFormat="24"
        :disabled="isLocked"
        placeholder="--:--"
        @input="handleCheckOutInput"
        @keydown="(e) => { if (!/[0-9]|Backspace|Delete|Tab|Enter/.test(e.key)) e.preventDefault(); }"
      />
    </div>

    <!-- SAISIE DE LA PAUSE -->
    <div class="field">
      <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block"
        >Pause (min)</label
      >
      <InputText
        v-model="form.break_duration"
        type="number"
        :disabled="isLocked"
      />
    </div>

    <!-- ACTIONS -->
    <div class="flex justify-end gap-2 mt-4 pt-3 border-t">
      <Button
        label="Annuler"
        class="p-button-text p-button-secondary text-xs"
        @click="$emit('close')"
      />
      <Button
        v-if="!isLocked"
        :label="data.isBulk ? 'Appliquer au groupe' : 'Enregistrer'"
        icon="pi pi-check"
        class="p-button-sm font-bold"
        :loading="form.processing"
        @click="submit"
      />
    </div>
  </div>
</template>
