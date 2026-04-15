export const VAD_CONFIG = {
  fftSize: 1024,
  minRms: 0.015,
  thresholdFactor: 2.0,
  silenceMs: 4000,
  warmupMs: 800,
  smoothFactor: 0.85,
};

export const MIC_GAIN = 1.8;

// Shared mutable state across modules
export const state = {
  isRecording: false,
  currentLanguage: "uz",
  mapViewState: { scale: 1, panX: 0, panY: 0 },
  mapPanZoomReady: false,
  lastUserMessage: "",
  flightsCacheState: { data: null, ts: 0 },
  lastAssistantResponseData: null,

  complaintRecorder: null,
  complaintStream: null,
  complaintChunks: [],
  isComplaintRecording: false,

  ttsAbort: false,
  currentAudio: null,
  currentAudioUrl: null,
  currentAudioSource: null,

  audioContext: null,
  analyser: null,
  microphone: null,
  inputAudioContext: null,
  micGainNode: null,
  micHighpass: null,
  micCompressor: null,
  mediaRecorder: null,

  cameraStream: null,
  isCameraReady: false,
  captureInProgress: false,
  lastCaptureTime: 0,

  scene: null,
  globeCamera: null,
  renderer: null,
  hologram: null,
  particleSystem: null,
  outputAnalyser: null,
  outputDataArray: null,
};
