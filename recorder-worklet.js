class RecorderWorklet extends AudioWorkletProcessor {
  constructor() {
    super();
    this.buffer = [];
    this.port.onmessage = (event) => {
      if (event.data === "clear") {
        this.buffer = [];
      }
    };
  }

  process(inputs, outputs, parameters) {
    const input = inputs[0];
    if (input.length > 0) {
      const channelData = input[0];
      // Float32Array ma'lumotni kopyasini olamiz
      this.port.postMessage(new Float32Array(channelData));
    }
    return true;
  }
}

registerProcessor("recorder-worklet", RecorderWorklet);
