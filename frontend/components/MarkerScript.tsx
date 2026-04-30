import Script from 'next/script'

export function MarkerScript() {
  const projectId = process.env.NEXT_PUBLIC_MARKER_PROJECT_ID
  const enabled = process.env.NEXT_PUBLIC_ENABLE_MARKER === 'true'

  if (!enabled || !projectId) {
    return null
  }

  const snippet = `
    window.markerConfig = {
      project: '${projectId}',
      source: 'snippet'
    };

    !function(e,r,a){if(!e.__Marker){e.__Marker={};var t=[],n={__cs:t};["show","hide","isVisible","capture","cancelCapture","unload","reload","isExtensionInstalled","setReporter","clearReporter","setCustomData","on","off"].forEach(function(e){n[e]=function(){var r=Array.prototype.slice.call(arguments);r.unshift(e),t.push(r)}}),e.Marker=n;var s=r.createElement("script");s.async=1,s.src="https://edge.marker.io/latest/shim.js";var i=r.getElementsByTagName("script")[0];i.parentNode.insertBefore(s,i)}}(window,document);
  `

  return (
    <Script
      id="marker-io"
      strategy="afterInteractive"
      dangerouslySetInnerHTML={{ __html: snippet }}
    />
  )
}
