{{-- Patient picker for the clinical forms. Needs $patients, $selectedPatient, $id. --}}
<select id="{{ $id }}" name="patient_uid" class="form-select" required>
    <option value="" disabled @selected(old('patient_uid', $selectedPatient) === '')>Choose a patient</option>
    @foreach ($patients as $p)
        <option value="{{ $p['uid'] }}" @selected(old('patient_uid', $selectedPatient) === $p['uid'])>{{ $p['name'] }}</option>
    @endforeach
</select>
